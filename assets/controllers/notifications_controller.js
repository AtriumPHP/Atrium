import { Controller } from '@hotwired/stimulus';

/*
 * Atrium toast controller — client-local concerns only (timing + animation).
 * All notification content and state is server-authored by the Atrium:Notifications
 * Live Component; this controller never creates notifications.
 *
 * Each toast element carries:
 *   data-atrium--notifications-target="toast"
 *   data-notification-id="<id>"
 *   data-duration="<ms|''>"   (empty = persistent)
 * Dismissal is the Live Component's dismiss(id), triggered by the timer or the
 * close button (which carries the Live action attribute in the template). This
 * controller only animates and arms/pauses the timer.
 */
export default class extends Controller {
    static targets = ['toast'];

    toastTargetConnected(el) {
        requestAnimationFrame(() => el.classList.remove('opacity-0', 'translate-y-2'));

        const duration = parseInt(el.dataset.duration || '', 10);
        if (!Number.isNaN(duration) && duration > 0) {
            this._arm(el, duration);
            el.addEventListener('mouseenter', () => this._pause(el));
            el.addEventListener('mouseleave', () => this._arm(el, duration));
        }
    }

    _arm(el, duration) {
        this._pause(el);
        el._atriumTimer = window.setTimeout(() => this._dismiss(el), duration);
    }

    _pause(el) {
        if (el._atriumTimer) {
            window.clearTimeout(el._atriumTimer);
            el._atriumTimer = null;
        }
    }

    _dismiss(el) {
        el.classList.add('opacity-0', 'translate-y-2');
        const trigger = el.querySelector('[data-atrium-dismiss]');
        window.setTimeout(() => trigger && trigger.click(), 150);
    }
}
