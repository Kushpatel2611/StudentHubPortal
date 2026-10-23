document.addEventListener('DOMContentLoaded', () => {
  const nav = document.querySelector('nav');
  const menuButton = document.querySelector('.hamburger-menu');

  if (!nav || !menuButton) {
    return;
  }

  const navList = nav.querySelector('ul');

  if (!navList) {
    return;
  }

  const setMenuState = (isOpen) => {
    menuButton.setAttribute('aria-expanded', String(isOpen));
    navList.classList.toggle('is-open', isOpen);
    nav.classList.toggle('is-open', isOpen);
  };

  const toggleMenu = () => {
    const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
    setMenuState(!isExpanded);
  };

  menuButton.addEventListener('click', toggleMenu);

  menuButton.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      toggleMenu();
    }
  });

  setMenuState(window.innerWidth > 700);

  window.addEventListener('resize', () => {
    if (window.innerWidth > 700) {
      setMenuState(true);
    } else {
      const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
      if (!isExpanded) {
        setMenuState(false);
      }
    }
  });
});
