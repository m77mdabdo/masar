import './bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

/*
 * Alpine is the entire client-side budget. The site is server-rendered; this
 * drives the mega-menu, the mobile drawer and the article table of contents,
 * and nothing else.
 */
/*
 * The focus plugin is here for one reason: the mobile drawer declares
 * aria-modal, and a modal that does not trap focus lets a keyboard user tab
 * straight out of it into the page behind — which screen readers still announce
 * as hidden. x-trap also restores focus to the trigger on close.
 */
Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();
