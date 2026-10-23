document.addEventListener('DOMContentLoaded', () => {
  const modal = document.querySelector('.modal');
  const triggers = document.querySelectorAll('[data-open-modal]');

  if (!modal || triggers.length === 0) {
    return;
  }

  const closeButtons = modal.querySelectorAll('[data-close-modal]');
  const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
  let lastFocusedElement = null;

  const getFocusableElements = () => {
    return Array.from(modal.querySelectorAll(focusableSelector)).filter((element) => {
      return !element.hasAttribute('disabled') && element.offsetParent !== null;
    });
  };

  const setModalState = (isOpen) => {
    modal.hidden = !isOpen;
    modal.setAttribute('aria-hidden', String(!isOpen));
    document.body.classList.toggle('modal-open', isOpen);
  };

  const focusInitialElement = () => {
    const focusableElements = getFocusableElements();
    const elementToFocus = focusableElements.find((element) => !element.hasAttribute('data-close-modal')) || focusableElements[0];

    if (elementToFocus) {
      elementToFocus.focus();
    }
  };

  const closeModal = () => {
    setModalState(false);

    if (lastFocusedElement) {
      lastFocusedElement.focus();
    }
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', () => {
      lastFocusedElement = trigger;
      setModalState(true);
      focusInitialElement();
    });
  });

  closeButtons.forEach((button) => button.addEventListener('click', closeModal));

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (modal.hidden) {
      return;
    }

    if (event.key === 'Escape') {
      closeModal();
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    const focusableElements = getFocusableElements();

    if (focusableElements.length === 0) {
      event.preventDefault();
      return;
    }

    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];

    if (event.shiftKey && document.activeElement === firstFocusable) {
      event.preventDefault();
      lastFocusable.focus();
      return;
    }

    if (!event.shiftKey && document.activeElement === lastFocusable) {
      event.preventDefault();
      firstFocusable.focus();
    }
  });

  setModalState(false);
});
