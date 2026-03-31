<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ForumArchiveController extends Controller
{
    public function index()
    {
        $data = $this->loadData();
        if (!$data) {
            abort(500, 'Forum archive data not found.');
        }

        $forums = [];
        foreach ($data['forums'] as $id => $name) {
            $forumTopics = array_values(array_filter($data['topics'], fn ($t) => $t['forum_id'] == $id));
            if (count($forumTopics) > 0) {
                usort($forumTopics, fn ($a, $b) => strcasecmp($a['title'], $b['title']));
                // Strip post content for index - only send title and post count
                $forumTopics = array_map(fn ($t) => [
                    'title' => $t['title'],
                    'post_count' => count($t['posts']),
                    'id' => $t['forum_id'] . '-' . crc32($t['title']),
                ], $forumTopics);

                $forums[] = [
                    'id' => $id,
                    'name' => $name,
                    'topic_count' => count($forumTopics),
                    'topics' => $forumTopics,
                ];
            }
        }

        usort($forums, fn ($a, $b) => $b['topic_count'] <=> $a['topic_count']);

        return Inertia::render('ForumArchive/Index', [
            'forums' => $forums,
            'totalTopics' => count($data['topics']),
        ]);
    }

    public function show(string $topicId)
    {
        $data = $this->loadData();
        if (!$data) {
            abort(404);
        }

        foreach ($data['topics'] as $topic) {
            $id = $topic['forum_id'] . '-' . crc32($topic['title']);
            if ($id === $topicId) {
                $forumName = $data['forums'][$topic['forum_id']] ?? 'Unknown';

                return Inertia::render('ForumArchive/Topic', [
                    'topic' => $topic,
                    'forumName' => $forumName,
                ]);
            }
        }

        abort(404, 'Topic not found.');
    }

    private function loadData(): ?array
    {
        $path = 'q3df-forum/forum_data.json';

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }
}
