<?php

namespace Tests\Feature;

use App\Models\ServerdemoValidatorApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The apply form locked people out of their own application: the throttle
 * counted rejected attempts, so five typos cost an hour. These pin down what
 * a person actually does - get it wrong, fix it, submit - and that the limit
 * is only reached by someone hammering the endpoint.
 */
class ServerdemoValidatorApplyTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function payload(string $motivation = null): array
    {
        return [
            'motivation' => $motivation ?? str_repeat('I want to help review reported runs. ', 3),
            'experience' => 'Years of defrag.',
            'availability' => 'Evenings CET',
            'contact' => 'someone#0001',
        ];
    }

    public function test_a_valid_application_is_stored(): void
    {
        $user = $this->applicant();

        $this->actingAs($user)
            ->post(route('serverdemo-validators.apply'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('serverdemo_validator_applications', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_short_answer_is_rejected_and_can_be_fixed(): void
    {
        $user = $this->applicant();

        // Four fumbles - one more than the old limit of five would have
        // survived once the real attempt is added.
        foreach (range(1, 4) as $ignored) {
            $this->actingAs($user)
                ->post(route('serverdemo-validators.apply'), $this->payload('too short'))
                ->assertSessionHasErrors('motivation');
        }

        $this->assertSame(0, ServerdemoValidatorApplication::where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->post(route('serverdemo-validators.apply'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ServerdemoValidatorApplication::where('user_id', $user->id)->count());
    }

    public function test_the_min_message_names_the_number(): void
    {
        $user = $this->applicant();

        $response = $this->actingAs($user)
            ->post(route('serverdemo-validators.apply'), $this->payload('too short'));

        $this->assertStringContainsString(
            '60 characters',
            session('errors')->first('motivation')
        );
    }

    public function test_a_second_application_is_refused_while_one_is_open(): void
    {
        $user = $this->applicant();

        $this->actingAs($user)->post(route('serverdemo-validators.apply'), $this->payload());

        $this->actingAs($user)
            ->post(route('serverdemo-validators.apply'), $this->payload())
            ->assertSessionHasErrors('motivation');

        $this->assertSame(1, ServerdemoValidatorApplication::where('user_id', $user->id)->count());
    }

    public function test_the_limit_still_stops_someone_hammering_it(): void
    {
        $user = $this->applicant();

        foreach (range(1, 20) as $ignored) {
            $this->actingAs($user)->post(route('serverdemo-validators.apply'), $this->payload('too short'));
        }

        $this->actingAs($user)
            ->post(route('serverdemo-validators.apply'), $this->payload())
            ->assertStatus(429);
    }

    public function test_a_throttled_inertia_post_goes_back_to_the_form(): void
    {
        $user = $this->applicant();

        foreach (range(1, 20) as $ignored) {
            $this->actingAs($user)->post(route('serverdemo-validators.apply'), $this->payload('too short'));
        }

        // What the browser sends. It must not get the full-page error screen.
        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('serverdemo-validators.apply'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasErrors('throttle');
    }

    /**
     * The limit is keyed on the user, not the address. One person exhausting
     * theirs must not touch anybody else - the announcement means a lot of
     * people arriving at once, several of them behind the same NAT.
     */
    public function test_one_person_hitting_the_limit_does_not_block_anyone_else(): void
    {
        $blocked = $this->applicant();

        foreach (range(1, 20) as $ignored) {
            $this->actingAs($blocked)->post(route('serverdemo-validators.apply'), $this->payload('too short'));
        }

        $this->actingAs($blocked)
            ->post(route('serverdemo-validators.apply'), $this->payload())
            ->assertStatus(429);

        // Nineteen other applicants, each fumbling the form twice first.
        foreach (range(1, 19) as $ignored) {
            $other = $this->applicant();

            $this->actingAs($other)->post(route('serverdemo-validators.apply'), $this->payload('too short'));
            $this->actingAs($other)->post(route('serverdemo-validators.apply'), $this->payload('still too short'));

            $this->actingAs($other)
                ->post(route('serverdemo-validators.apply'), $this->payload())
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('serverdemo_validator_applications', ['user_id' => $other->id]);
        }

        $this->assertSame(19, ServerdemoValidatorApplication::count());
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('serverdemo-validators.apply');

        parent::tearDown();
    }
}
