document.addEventListener('DOMContentLoaded', () => {
  const banner = document.querySelector('.notification-banner');

  if (!banner) {
    return;
  }

  const closeButton = banner.querySelector('.notification-close');

  if (closeButton) {
    closeButton.addEventListener('click', () => {
      banner.hidden = true;
    });
  }
});
