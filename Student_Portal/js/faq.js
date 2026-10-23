document.addEventListener('DOMContentLoaded', () => {
  const faqButtons = document.querySelectorAll('.faq-question');

  faqButtons.forEach((button) => {
    const answerId = button.getAttribute('aria-controls');
    const answer = answerId ? document.getElementById(answerId) : null;

    if (!answer) {
      return;
    }

    const updateState = (isOpen) => {
      button.setAttribute('aria-expanded', String(isOpen));
      button.classList.toggle('is-open', isOpen);
      answer.hidden = !isOpen;
      answer.classList.toggle('is-open', isOpen);
    };

    updateState(button.getAttribute('aria-expanded') === 'true');

    button.addEventListener('click', () => {
      const isOpen = button.getAttribute('aria-expanded') === 'true';
      updateState(!isOpen);
    });
  });
});
