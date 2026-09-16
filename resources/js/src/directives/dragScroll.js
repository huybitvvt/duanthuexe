const INTERACTIVE_SELECTOR = "a, button, input, select, textarea, label, [role='button']";

export default {
  bind(el) {
    const state = {
      pointerId: null,
      startX: 0,
      startScrollLeft: 0,
      moved: false
    };

    if (!el.hasAttribute("tabindex")) {
      el.setAttribute("tabindex", "0");
    }
    if (!el.hasAttribute("aria-label")) {
      el.setAttribute("aria-label", "Bảng có thể cuộn ngang");
    }
    el.classList.add("himoto-drag-scroll");

    const onPointerDown = event => {
      if (event.button !== 0 || event.pointerType === "touch") return;
      if (event.target.closest && event.target.closest(INTERACTIVE_SELECTOR)) return;

      state.pointerId = event.pointerId;
      state.startX = event.clientX;
      state.startScrollLeft = el.scrollLeft;
      state.moved = false;
      el.classList.add("is-dragging");
      if (el.setPointerCapture) el.setPointerCapture(event.pointerId);
    };

    const onPointerMove = event => {
      if (state.pointerId !== event.pointerId) return;
      const distance = event.clientX - state.startX;
      if (Math.abs(distance) > 4) state.moved = true;
      if (!state.moved) return;
      event.preventDefault();
      el.scrollLeft = state.startScrollLeft - distance;
    };

    const stopDragging = event => {
      if (state.pointerId === null || (event.pointerId != null && state.pointerId !== event.pointerId)) return;
      if (el.releasePointerCapture && el.hasPointerCapture && el.hasPointerCapture(state.pointerId)) {
        el.releasePointerCapture(state.pointerId);
      }
      state.pointerId = null;
      el.classList.remove("is-dragging");
    };

    const onClick = event => {
      if (!state.moved) return;
      event.preventDefault();
      event.stopPropagation();
      state.moved = false;
    };

    const onKeyDown = event => {
      if (event.target !== el || !["ArrowLeft", "ArrowRight"].includes(event.key)) return;
      event.preventDefault();
      el.scrollBy({
        left: event.key === "ArrowRight" ? 120 : -120,
        behavior: "smooth"
      });
    };

    el.__himotoDragScroll = { onPointerDown, onPointerMove, stopDragging, onClick, onKeyDown };
    el.addEventListener("pointerdown", onPointerDown);
    el.addEventListener("pointermove", onPointerMove);
    el.addEventListener("pointerup", stopDragging);
    el.addEventListener("pointercancel", stopDragging);
    el.addEventListener("click", onClick, true);
    el.addEventListener("keydown", onKeyDown);
  },
  unbind(el) {
    const handlers = el.__himotoDragScroll;
    if (!handlers) return;
    el.removeEventListener("pointerdown", handlers.onPointerDown);
    el.removeEventListener("pointermove", handlers.onPointerMove);
    el.removeEventListener("pointerup", handlers.stopDragging);
    el.removeEventListener("pointercancel", handlers.stopDragging);
    el.removeEventListener("click", handlers.onClick, true);
    el.removeEventListener("keydown", handlers.onKeyDown);
    delete el.__himotoDragScroll;
  }
};
