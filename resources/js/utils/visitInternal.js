import { router } from '@inertiajs/vue3';

/**
 * Keep an in-app visit when the link lives inside a translated sentence.
 *
 * A sentence broken by a link has to stay one key, or the translator gets
 * fragments and cannot move the link to where their language puts it - in
 * Czech it usually lands at the other end of the clause. That means the
 * anchor is written inside the key and rendered with v-html, so Vue never
 * sees it and an <Link> is not an option: what reaches the page is a plain
 * <a>, and a plain <a> to our own site is a full page load.
 *
 * So catch the click on the way up instead and hand the href to Inertia.
 * Only for our own paths, and only for a plain left click - anything the
 * reader did on purpose (new tab, middle click, download) is left alone.
 *
 * Put it on the element that carries the v-html, as a plain click handler.
 * No example is spelled out here on purpose: lang:sync cannot tell code from
 * comment, and a translation call written out in prose ends up as a key.
 */
export const visitInternal = (event) => {
    if (event.defaultPrevented || event.button !== 0) return;

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest?.('a');

    if (!link) return;

    const href = link.getAttribute('href');

    // Same-origin paths only. An absolute URL, a mail link, an anchor on the
    // page and anything aimed at another tab all belong to the browser.
    if (!href || !href.startsWith('/') || href.startsWith('//')) return;

    if (link.target || link.hasAttribute('download')) return;

    event.preventDefault();

    router.visit(href);
};
