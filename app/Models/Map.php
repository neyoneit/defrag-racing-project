<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;

class Map extends Model
{
    use HasFactory;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'author',
        'pk3',
        'pk3_size',
        'thumbnail',
        'physics',
        'gametype',
        'mod',
        'weapons',
        'items',
        'functions',
        'visible',
        'date_added',
        'cpm_average',
        'vq3_average'
    ];

    public function generateSubstrings($name) {
        $inputString = mb_convert_encoding($name, 'Windows-1252', "auto");
        $length = strlen($inputString);
        $result = [];
    
        for ($i = 0; $i <= $length; $i++) {
            $sub = substr($inputString, $i);

            if (strlen($sub) < 3) {
                break;
            }

            $result[] = $sub;
        }

        if (count($result) == 0) {
            $result[] = $inputString;
        }
    
        return $result;
    }

    public function toSearchableArray () {
        return [
            'id' => (string) $this->id,
            'name' => $this->generateSubstrings($this->name),
            'created_at' => $this->created_at->timestamp,
        ];
    }

    public function records () {
        return $this->hasMany(Record::class, 'mapname', 'name')->orderBy('date_set', 'DESC');
    }

    public function processRanks () {
        DB::beginTransaction();
        $records = Record::where('mapname', $this->name)->orderBy('time')->get()->groupBy('physics');

        foreach($records as $physics => $data) {
            $i = 1;
            $last_time = -1;
            $besttime = -1;

            if (count($data) > 0) {
                $besttime = $data[0]->time;
            }

            foreach($data as $record) {
                if ($last_time === -1) {
                    $record->rank = $i;
                    $record->besttime = $besttime;
                    $last_time = $record->time;
                    $i++;
                    $record->save();
                    continue;
                }

                if ($last_time === $record->time) {
                    $i--;
                    $record->rank = $i;
                    $record->besttime = $besttime;
                    $last_time = $record->time;
                    $i++;
                    $record->save();
                    continue;
                }

                $record->rank = $i;
                $record->besttime = $besttime;
                $last_time = $record->time;

                $i++;
                $record->save();
            }
        }

        DB::commit();
    }

    public function processAverageTime () {
        $records = Record::where('mapname', $this->name)->orderBy('time')->get()->groupBy('physics');

        foreach($records as $physics => $data) {
            $i = 0;
            $total = 0;

            foreach($data as $record) {
                $total += $record->time;
                $i++;
            }

            $average = $total / $i;

            if ($physics == 'vq3') {
                $this->vq3_average = $average;
            } else {
                $this->cpm_average = $average;
            }
        }

        $this->save();
    }

    public function processRecordsNumber () {
        $gametypes = [
            'run',
            'ctf1',
            'ctf2',
            'ctf3',
            'ctf4',
            'ctf5',
            'ctf6',
            'ctf7'
        ];

        
    }

    public function hasWeapon($weapon) {
        if (Str::contains($this->weapons, $weapon)) {
            return true;
        }

        return false;
    }

    public function isStrafe() {
        return !$this->weapons || trim($this->weapons) === '';
    }

    /**
     * Tags associated with this map
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'map_tag')
            ->withPivot('user_id')
            ->withTimestamps();
    }
}
