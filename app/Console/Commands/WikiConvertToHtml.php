<?php

namespace App\Console\Commands;

use App\Models\WikiPage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WikiConvertToHtml extends Command
{
    protected $signature = 'wiki:convert-to-html';
    protected $description = 'Convert wiki pages from Markdown to HTML';

    public function handle()
    {
        $pages = WikiPage::all();
        $count = 0;

        foreach ($pages as $page) {
            // Skip if already HTML (starts with < tag)
            if (Str::startsWith(trim($page->content), '<')) {
                $this->info("Skipping {$page->slug} - already HTML");
                continue;
            }

            $html = $this->markdownToHtml($page->content);
            $page->update(['content' => $html]);
            $count++;
            $this->info("Converted: {$page->slug} (" . strlen($html) . " bytes)");
        }

        // Also convert revisions
        $revisions = \App\Models\WikiRevision::all();
        foreach ($revisions as $rev) {
            if (!Str::startsWith(trim($rev->content), '<')) {
                $rev->update(['content' => $this->markdownToHtml($rev->content)]);
            }
        }

        $this->info("Converted {$count} pages to HTML.");
    }

    private function markdownToHtml(string $markdown): string
    {
        // Use a simple but comprehensive markdown->HTML converter
        $html = $markdown;

        // Pre-process: protect code blocks
        $codeBlocks = [];
        $html = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) use (&$codeBlocks) {
            $idx = count($codeBlocks);
            $lang = $m[1] ?: '';
            $code = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $codeBlocks[$idx] = "<pre><code" . ($lang ? " class=\"language-{$lang}\"" : "") . ">{$code}</code></pre>";
            return "%%CODEBLOCK{$idx}%%";
        }, $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Headers
        $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);

        // Horizontal rules
        $html = preg_replace('/^---+$/m', '<hr>', $html);

        // Bold and italic
        $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);

        // Images
        $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $html);

        // Tables
        $html = preg_replace_callback('/(\|.+\|)\n(\|[\s\-:|]+\|)\n((?:\|.+\|\n?)+)/m', function ($m) {
            $headerRow = trim($m[1]);
            $bodyRows = trim($m[3]);

            $headers = array_map('trim', explode('|', trim($headerRow, '|')));
            $headerHtml = '<tr>' . implode('', array_map(fn($h) => "<th>{$h}</th>", $headers)) . '</tr>';

            $rows = array_filter(explode("\n", $bodyRows));
            $bodyHtml = '';
            foreach ($rows as $row) {
                $cells = array_map('trim', explode('|', trim($row, '|')));
                $bodyHtml .= '<tr>' . implode('', array_map(fn($c) => "<td>{$c}</td>", $cells)) . '</tr>';
            }

            return "<table><thead>{$headerHtml}</thead><tbody>{$bodyHtml}</tbody></table>";
        }, $html);

        // Blockquotes
        $html = preg_replace_callback('/(?:^>\s?.+$\n?)+/m', function ($m) {
            $text = preg_replace('/^>\s?/m', '', $m[0]);
            return '<blockquote><p>' . trim($text) . '</p></blockquote>';
        }, $html);

        // Unordered lists
        $html = preg_replace_callback('/(?:^[-*]\s+.+$\n?)+/m', function ($m) {
            $items = preg_split('/^[-*]\s+/m', trim($m[0]));
            $items = array_filter($items);
            $lis = implode('', array_map(fn($i) => '<li>' . trim($i) . '</li>', $items));
            return "<ul>{$lis}</ul>";
        }, $html);

        // Ordered lists
        $html = preg_replace_callback('/(?:^\d+\.\s+.+$\n?)+/m', function ($m) {
            $items = preg_split('/^\d+\.\s+/m', trim($m[0]));
            $items = array_filter($items);
            $lis = implode('', array_map(fn($i) => '<li>' . trim($i) . '</li>', $items));
            return "<ol>{$lis}</ol>";
        }, $html);

        // Paragraphs - wrap remaining text blocks
        $lines = explode("\n", $html);
        $result = [];
        $inParagraph = false;
        $paragraphLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines
            if ($trimmed === '') {
                if ($inParagraph && !empty($paragraphLines)) {
                    $result[] = '<p>' . implode(' ', $paragraphLines) . '</p>';
                    $paragraphLines = [];
                    $inParagraph = false;
                }
                continue;
            }

            // If line starts with HTML tag, it's already processed
            if (preg_match('/^<(h[1-6]|ul|ol|li|table|thead|tbody|tr|th|td|pre|blockquote|hr|div|p)/', $trimmed) ||
                preg_match('/^%%CODEBLOCK/', $trimmed)) {
                if ($inParagraph && !empty($paragraphLines)) {
                    $result[] = '<p>' . implode(' ', $paragraphLines) . '</p>';
                    $paragraphLines = [];
                    $inParagraph = false;
                }
                $result[] = $trimmed;
                continue;
            }

            // Regular text line
            $inParagraph = true;
            $paragraphLines[] = $trimmed;
        }

        if (!empty($paragraphLines)) {
            $result[] = '<p>' . implode(' ', $paragraphLines) . '</p>';
        }

        $html = implode("\n", $result);

        // Restore code blocks
        foreach ($codeBlocks as $idx => $block) {
            $html = str_replace("%%CODEBLOCK{$idx}%%", $block, $html);
        }

        return $html;
    }
}
