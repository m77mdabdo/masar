/*
 * The big story: a slow zoom-out on each photograph, then a cross-fade to the
 * next (owner's rulings, Tasks 2.10 and 2.11).
 *
 * - Dwell 5s, the owner's floor: under it the hero stops reading as a
 *   publication and starts reading as a billboard. Do not go lower without
 *   asking. WCAG 2.2.2's five seconds is measured over the whole loop, not
 *   one slide, and the loop never ends: the pause control is what makes this
 *   conform at any dwell.
 * - Fade 480ms, inside the owner's 450–500.
 * - The drift runs dwell + fade (5.48s), so the outgoing picture is still
 *   settling under the incoming one rather than stopping dead.
 * - Nothing moves until the lead image has decoded: the drift must never
 *   compete with the LCP paint.
 * - One switch, `playing`: drift and advance stop and start together. Hover
 *   and keyboard focus hold it; leaving releases it. Prev/next is the reader
 *   taking control: advance stops for the rest of the session and is not
 *   handed back unless they press play.
 * - prefers-reduced-motion: nothing moves at all; the controls remain.
 * - Only the next slide is fetched, during the current one's dwell. A fade
 *   never starts until its picture has decoded.
 */
const DWELL = 5000;
const FADE = 480;
const STOPPED = 'masar.hero.manual';

const bigStory = (n) => ({
    i: 0,
    n,
    prev: -1,
    next: -1,
    playing: false,
    // Until the lead image decodes the carousel is about to play, not
    // stopped: the control already reads "pause".
    starting: true,
    reduced: false,
    held: { hover: false, focus: false },
    // Slides whose drift has started this turn. Pausing freezes them where
    // they are (animation-play-state); removing the class would snap the
    // picture back to its resting scale.
    drift: [],
    // Slide 0 is rendered at the drift's start scale, so the drift begins
    // without a jump. Cleared the first time slide 0 leaves.
    lead: true,
    timer: null,
    deadline: 0,
    remaining: DWELL,
    status: '',

    init() {
        const query = matchMedia('(prefers-reduced-motion: reduce)');
        this.reduced = query.matches;
        query.addEventListener('change', (e) => {
            this.reduced = e.matches;
            if (e.matches) this.stop();
        });

        let stopped = false;
        try { stopped = sessionStorage.getItem(STOPPED) === '1'; } catch { /* storage off: default on */ }

        // A reader who stopped it earlier this session keeps slide 0 at its
        // rendered scale rather than watching it snap.
        if (this.reduced || stopped) {
            this.starting = false;
            return;
        }

        this.decoded(0).then(() => {
            if (!this.starting) return;
            this.starting = false;
            this.playing = true;
            this.drift = [0];
            this.queue();
            // Half a dwell in: the page's own images are fetched by then,
            // and the next slide still has 2.5s to arrive. On the
            // Lighthouse mobile profile the largest (262 KB at 1440) decodes
            // ~1s before its fade; slower than that, the fade waits for it.
            setTimeout(() => { if (this.playing) this.arm((this.i + 1) % this.n); }, DWELL / 2);
        });
    },

    // The bindings live here rather than in the markup: they only matter once
    // this file has loaded, and in the markup they were bytes in front of the
    // LCP image.
    get root() {
        const c = this;
        return {
            ':data-paused': () => c.paused,
            '@mouseenter': () => c.hold('hover', true),
            '@mouseleave': () => c.hold('hover', false),
            '@focusin': () => c.hold('focus', true),
            '@focusout': (e) => c.leave(e),
        };
    },

    get announcer() {
        return { ':aria-live': () => this.live };
    },

    photo(k) {
        const c = this;
        return {
            ':class': () => ({ 'is-armed': c.armed(k), 'is-drifting': c.drifting(k) }),
            ':style': () => ({ display: c.shown(k) ? '' : 'none', opacity: c.i === k || c.prev === k ? 1 : 0, zIndex: c.i === k ? 1 : 0 }),
        };
    },

    get toggler() {
        const c = this;
        return {
            '@click': () => c.toggle(),
            'x-show': () => !c.reduced,
            ':aria-label': () => (c.playing || c.starting ? 'إيقاف العرض التلقائي' : 'تشغيل العرض التلقائي'),
        };
    },

    get paused() {
        return !this.playing || this.held.hover || this.held.focus;
    },

    // Live announcements are for the reader who moved the slide, not for a
    // loop: while it advances on its own the region is off.
    get live() {
        return this.playing ? 'off' : 'polite';
    },

    shown(k) {
        return k === this.i || k === this.prev || k === this.next;
    },

    drifting(k) {
        return this.drift.includes(k);
    },

    armed(k) {
        return (k === 0 && this.lead) || (this.playing && k === this.next);
    },

    img(k) {
        return this.$root.querySelectorAll('.hero-photo')[k]?.querySelector('img');
    },

    decoded(k) {
        const img = this.img(k);
        if (!img) return Promise.resolve();
        img.loading = 'eager';
        return img.decode().catch(() => {});
    },

    // Puts the next slide in the tree, invisible, so its lazy image loads
    // during this one's dwell instead of up front.
    arm(k) {
        if (k === this.i) return;
        this.next = k;
        this.$nextTick(() => this.decoded(k));
    },

    queue(ms = DWELL) {
        clearTimeout(this.timer);
        this.remaining = ms;
        if (this.paused) return;
        this.deadline = performance.now() + ms;
        this.timer = setTimeout(() => this.advance(), ms);
    },

    hold(which, on) {
        const was = this.paused;
        this.held[which] = on;
        if (!this.playing || was === this.paused) return;
        if (this.paused) {
            clearTimeout(this.timer);
            this.remaining = Math.max(0, this.deadline - performance.now());
        } else {
            this.queue(this.remaining);
        }
    },

    leave(event) {
        if (!this.$root.contains(event.relatedTarget)) this.hold('focus', false);
    },

    async show(k) {
        if (k === this.i) return;
        this.next = k;
        await this.$nextTick();
        await this.decoded(k);
        const from = this.i;
        // The outgoing picture keeps settling under the incoming one; the
        // incoming one drifts only if the carousel is running.
        this.drift = this.drift.filter((x) => x === from).concat(this.playing ? [k] : []);
        this.prev = from;
        this.i = k;
        this.next = -1;
        this.lead = false;
        setTimeout(() => {
            if (this.i !== k) return;
            this.prev = -1;
            this.drift = this.drift.filter((x) => x === k);
        }, this.reduced ? 0 : FADE);
    },

    async advance() {
        await this.show((this.i + 1) % this.n);
        if (!this.playing) return;
        this.queue();
        setTimeout(() => this.arm((this.i + 1) % this.n), FADE);
    },

    go(d) {
        this.stop(true);
        // The slide change is announced by the live region; a stale
        // "resumed" here would contradict it.
        this.status = '';
        this.show((this.i + d + this.n) % this.n);
    },

    stop(byReader = false) {
        clearTimeout(this.timer);
        this.playing = false;
        this.starting = false;
        this.next = -1;
        if (byReader) {
            try { sessionStorage.setItem(STOPPED, '1'); } catch { /* still stops for this page */ }
        }
    },

    toggle() {
        if (this.playing || this.starting) {
            this.stop(true);
            this.status = 'أُوقف العرض التلقائي';
            return;
        }
        try { sessionStorage.removeItem(STOPPED); } catch { /* nothing to clear */ }
        // Pressing play is the reader's own instruction: it overrides the
        // hold their pointer or focus is placing on the section right now.
        this.held.hover = false;
        this.held.focus = false;
        this.playing = true;
        this.status = 'استؤنف العرض التلقائي';
        this.queue();
        this.arm((this.i + 1) % this.n);
    },
});

/*
 * Fetched after the window's load event by the component's own loader, and
 * registered here: until now the section was `x-ignore`d, which left exactly
 * the server-rendered frame — slide one, still, at the drift's start scale.
 */
// WCAG 2.2.2: the drift and the advance run longer than five seconds, so they
// get a stop. It takes the next button's own class string, so it is the same
// circle, border and hover as its neighbours by construction. The label says
// what pressing it will do; the status line says what it did. Hidden under
// reduced motion, where nothing moves to stop.
const PAUSE = '<rect x="2.5" y="2" width="2.25" height="8" rx="0.5"/><rect x="7.25" y="2" width="2.25" height="8" rx="0.5"/>';
const PLAY = '<path d="M3.5 2.2v7.6a.5.5 0 0 0 .76.43l6.1-3.8a.5.5 0 0 0 0-.86l-6.1-3.8a.5.5 0 0 0-.76.43z"/>';
function addToggle(section) {
    const next = section.querySelector('[data-next]');
    if (!next) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = next.className;
    button.setAttribute('aria-label', 'إيقاف العرض التلقائي');
    button.setAttribute('x-bind', 'toggler');
    button.innerHTML = `<svg x-show="playing || starting" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">${PAUSE}</svg>`
        + `<svg x-show="!(playing || starting)" style="display:none" class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">${PLAY}</svg>`;
    const status = document.createElement('span');
    status.className = 'sr-only';
    status.setAttribute('role', 'status');
    status.setAttribute('x-text', 'status');
    next.after(button, status);
}

window.Alpine.data('bigStory', bigStory);
document.querySelectorAll('[data-carousel]').forEach((el) => {
    el.removeAttribute('x-ignore');
    // Alpine clears its ignore flag from a MutationObserver callback, which
    // is a microtask; initTree called synchronously still sees the flag and
    // returns without doing anything. A task runs after every microtask.
    // The toggle is added only once the section has its data: added earlier,
    // the observer initialised it with no scope to bind to.
    setTimeout(() => {
        window.Alpine.initTree(el);
        addToggle(el);
    });
});
