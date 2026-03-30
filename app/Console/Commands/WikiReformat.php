<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WikiReformat extends Command
{
    protected $signature = 'wiki:reformat';
    protected $description = 'Reformat all wiki pages with modern styling';

    public function handle()
    {
        $pages = DB::table('wiki_pages')->get();

        foreach ($pages as $page) {
            $content = $page->content;
            $content = $this->reformatContent($content, $page->slug);
            DB::table('wiki_pages')->where('id', $page->id)->update(['content' => $content]);
            $this->info("Reformatted: {$page->slug}");
        }

        $this->info('Done. All ' . $pages->count() . ' pages reformatted.');
    }

    private function reformatContent(string $html, string $slug): string
    {
        // Remove old credits
        $html = preg_replace('/<hr[^>]*><div style="[^"]*margin-top.*?<\/div>/s', '', $html);
        $html = preg_replace('/<hr[^>]*><div style="[^"]*padding: 1rem.*?Sources.*?<\/div>/s', '', $html);
        $html = preg_replace('/<hr[^>]*>\s*<div[^>]*>\s*<strong[^>]*>Sources &amp; Credits<\/strong>.*?<\/div>/s', '', $html);

        // Strip ALL existing inline styles to start fresh
        $html = preg_replace('/\s*style="[^"]*"/', '', $html);

        // Remove old broken navigation divs
        $html = preg_replace('/<div[^>]*>[\s]*<p[^>]*>Quick Navigation<\/p>.*?<\/div>[\s]*<\/div>/s', '', $html);
        $html = preg_replace('/<div[^>]*>[\s]*<p[^>]*>Jump to section<\/p>.*?<\/div>[\s]*<\/div>/s', '', $html);

        // =============================================
        // UNIVERSAL TRANSFORMS
        // =============================================

        // Wrap all tables in a responsive container with modern styling
        $html = preg_replace(
            '/<table>/',
            '<div style="overflow-x: auto; margin: 1rem 0; border-radius: 0.5rem; border: 1px solid rgba(55, 65, 81, 0.5);"><table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">',
            $html
        );
        $html = str_replace('</table>', '</table></div>', $html);

        // Style table headers
        $html = preg_replace(
            '/<th>/',
            '<th style="background: rgba(30, 41, 59, 0.8); padding: 0.6rem 0.8rem; text-align: left; font-size: 0.8em; font-weight: 600; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid rgba(59, 130, 246, 0.3);">',
            $html
        );

        // Style table cells
        $html = preg_replace(
            '/<td>/',
            '<td style="padding: 0.5rem 0.8rem; border-bottom: 1px solid rgba(55, 65, 81, 0.3); color: #9ca3af; font-size: 0.875em;">',
            $html
        );

        // Style h2 as section headers with accent line
        $html = preg_replace(
            '/<h2>(.*?)<\/h2>/',
            '<h2 style="font-size: 1.5rem; font-weight: 700; color: #e2e8f0; margin: 2.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(59, 130, 246, 0.4);">$1</h2>',
            $html
        );

        // Style h3 as subsection headers
        $html = preg_replace(
            '/<h3>(.*?)<\/h3>/',
            '<h3 style="font-size: 1.2rem; font-weight: 600; color: #cbd5e1; margin: 1.8rem 0 0.8rem; padding-left: 0.75rem; border-left: 3px solid rgba(59, 130, 246, 0.5);">$1</h3>',
            $html
        );

        // Style h4
        $html = preg_replace(
            '/<h4>(.*?)<\/h4>/',
            '<h4 style="font-size: 1.05rem; font-weight: 600; color: #94a3b8; margin: 1.2rem 0 0.5rem;">$1</h4>',
            $html
        );

        // Style paragraphs
        $html = preg_replace(
            '/<p>/',
            '<p style="color: #9ca3af; line-height: 1.7; margin-bottom: 1rem;">',
            $html
        );

        // Style code blocks (pre)
        $html = preg_replace(
            '/<pre>/',
            '<pre style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(55, 65, 81, 0.5); border-radius: 0.5rem; padding: 1rem; margin: 1rem 0; overflow-x: auto; font-size: 0.85em;">',
            $html
        );

        // Style inline code
        $html = preg_replace(
            '/<code>(?!.*<\/pre)/',
            '<code style="background: rgba(15, 23, 42, 0.6); color: #67e8f9; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.875em;">',
            $html
        );

        // Style blockquotes
        $html = preg_replace(
            '/<blockquote>/',
            '<blockquote style="border-left: 4px solid rgba(59, 130, 246, 0.4); padding: 0.75rem 1rem; margin: 1rem 0; background: rgba(30, 41, 59, 0.3); border-radius: 0 0.5rem 0.5rem 0;">',
            $html
        );

        // Style unordered lists
        $html = preg_replace(
            '/<ul>/',
            '<ul style="list-style: none; padding: 0; margin: 1rem 0;">',
            $html
        );

        // Style list items with custom bullets
        $html = preg_replace(
            '/<li>/',
            '<li style="padding: 0.3rem 0 0.3rem 1.5rem; position: relative; color: #9ca3af; line-height: 1.6; border-bottom: 1px solid rgba(55, 65, 81, 0.15);"><span style="position: absolute; left: 0; color: #3b82f6;">&#9656;</span>',
            $html
        );

        // Style ordered lists
        $html = preg_replace(
            '/<ol>/',
            '<ol style="padding-left: 1.5rem; margin: 1rem 0; color: #9ca3af; counter-reset: item;">',
            $html
        );

        // Style links
        $html = preg_replace(
            '/<a href="([^"]*)"([^>]*)>/',
            '<a href="$1" style="color: #60a5fa; text-decoration: none; border-bottom: 1px solid rgba(96, 165, 250, 0.3); transition: all 0.2s;"$2>',
            $html
        );

        // Style strong/bold
        $html = preg_replace(
            '/<strong>/',
            '<strong style="color: #e2e8f0; font-weight: 600;">',
            $html
        );

        // Style em/italic
        $html = preg_replace(
            '/<em>/',
            '<em style="color: #94a3b8;">',
            $html
        );

        // Style hr
        $html = str_replace('<hr>', '<hr style="border: none; border-top: 1px solid rgba(55, 65, 81, 0.5); margin: 2rem 0;">', $html);

        // =============================================
        // PAGE-SPECIFIC TRANSFORMS
        // =============================================

        if ($slug === 'console-commands') {
            $html = $this->reformatConsoleCommands($html);
        }

        // =============================================
        // ADD CREDITS FOOTER
        // =============================================
        $html .= $this->getCreditsFooter();

        return $html;
    }

    private function reformatConsoleCommands(string $html): string
    {
        // 1. Add id anchors to ALL h2 and h3 headings
        $html = preg_replace_callback('/<h([23])[^>]*>(.*?)<\/h[23]>/s', function ($m) {
            $level = $m[1];
            $title = strip_tags($m[2]);
            $id = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($title)));
            $id = trim($id, '-');
            $style = $level === '2'
                ? 'font-size: 1.5rem; font-weight: 700; color: #e2e8f0; margin: 2.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(59, 130, 246, 0.4);'
                : 'font-size: 1.2rem; font-weight: 600; color: #cbd5e1; margin: 1.8rem 0 0.8rem; padding-left: 0.75rem; border-left: 3px solid rgba(59, 130, 246, 0.5);';
            return "<h{$level} id=\"{$id}\" style=\"{$style}\">{$m[2]}</h{$level}>";
        }, $html);

        // 2. Remove the redundant "Categories" heading
        $html = preg_replace('/<h3[^>]*id="categories"[^>]*>.*?<\/h3>/', '', $html);

        // 3. Build quick navigation from actual headings with their real IDs
        $toc = '<div style="background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(55, 65, 81, 0.5); border-radius: 0.75rem; padding: 1.25rem; margin: 1.5rem 0;">'
            . '<p style="color: #e2e8f0; font-weight: 600; margin-bottom: 0.75rem; font-size: 0.9rem; line-height: 1.7;">Jump to section</p>'
            . '<div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">';

        // Add h2 links (Commands, Variables)
        preg_match_all('/<h2[^>]*id="([^"]*)"[^>]*>(.*?)<\/h2>/s', $html, $h2Matches);
        foreach ($h2Matches[1] as $i => $id) {
            $label = strip_tags($h2Matches[2][$i]);
            if ($label === 'Legend') continue;
            $toc .= '<a href="#' . $id . '" style="display: inline-block; padding: 0.3rem 0.65rem; background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-radius: 0.375rem; font-size: 0.8em; font-weight: 600; text-decoration: none; border: 1px solid rgba(59, 130, 246, 0.3);">' . htmlspecialchars($label) . '</a>';
        }

        // Add h3 category links using their ACTUAL IDs from the content
        preg_match_all('/<h3[^>]*id="([^"]*)"[^>]*>(.*?)<\/h3>/s', $html, $navMatches);
        foreach ($navMatches[1] as $i => $id) {
            $label = strip_tags($navMatches[2][$i]);
            $toc .= '<a href="#' . $id . '" style="display: inline-block; padding: 0.25rem 0.6rem; background: rgba(59, 130, 246, 0.1); color: #93c5fd; border-radius: 0.375rem; font-size: 0.75em; font-family: monospace; text-decoration: none;">' . htmlspecialchars($label) . '</a>';
        }

        $toc .= '</div></div>';

        // 4. Insert navigation after the h1 title (before Legend)
        $html = preg_replace('/(<h1>.*?<\/h1>.*?<p[^>]*>.*?<\/p>)/', '$1' . $toc, $html, 1);

        // 5. Remove the old broken navigation if exists
        $html = preg_replace('/<div style="[^"]*">[\s]*<p[^>]*>Quick Navigation<\/p>.*?<\/div>[\s]*<\/div>/s', '', $html);

        // 6. Add alternating row colors
        $html = preg_replace_callback('/<tbody>(.*?)<\/tbody>/s', function ($m) {
            $rows = preg_split('/(?=<tr)/', $m[1]);
            $result = '';
            $i = 0;
            foreach ($rows as $row) {
                if (trim($row) === '') continue;
                $bg = ($i % 2 === 0) ? 'rgba(15, 23, 42, 0.3)' : 'transparent';
                $row = preg_replace('/<tr([^>]*)>/', '<tr style="background: ' . $bg . ';"$1>', $row, 1);
                $result .= $row;
                $i++;
            }
            return '<tbody>' . $result . '</tbody>';
        }, $html);

        return $html;
    }

    private function getCreditsFooter(): string
    {
        return '<hr style="border: none; border-top: 1px solid rgba(55, 65, 81, 0.5); margin: 3rem 0 1.5rem;">
<div style="padding: 1rem 1.25rem; background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(55, 65, 81, 0.3); border-radius: 0.5rem; font-size: 0.8em; color: #64748b; line-height: 1.6;">
<strong style="color: #94a3b8;">Sources &amp; Credits</strong><br>
Content compiled from: <a href="https://q3df.org/wiki?p=1" style="color: #60a5fa;">q3df.org wiki</a> (CC BY-SA, authors: &lt;hk&gt;, muckster, Ducky, Kairos, Timothy, Raack, amt-morbus, nlxajA, [WWWD]newbrict, 14K Inc.), community knowledge bases, and original research.<br>
This wiki is community-editable. If you find errors or want to contribute, click Edit above.
</div>';
    }
}
