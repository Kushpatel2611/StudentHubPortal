document.addEventListener('DOMContentLoaded', () => {
  const rootElement = document.documentElement;
  const themeToggle = document.querySelector('.theme-toggle');

  if (!themeToggle) {
    return;
  }

  const applyTheme = (theme) => {
    const selectedTheme = theme === 'dark' ? 'dark' : 'light';
    rootElement.setAttribute('data-theme', selectedTheme);
    themeToggle.setAttribute('aria-label', selectedTheme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
    themeToggle.textContent = selectedTheme === 'dark' ? 'Light Theme' : 'Dark Theme';
  };

  try {
    const savedTheme = localStorage.getItem('charusat-theme');
    if (savedTheme) {
      applyTheme(savedTheme);
    } else {
      applyTheme('light');
    }
  } catch (error) {
    applyTheme('light');
  }

  themeToggle.addEventListener('click', () => {
    const currentTheme = rootElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

    applyTheme(nextTheme);

    try {
      localStorage.setItem('charusat-theme', nextTheme);
    } catch (error) {
      // Theme preference cannot be stored in this browser.
    }
  });
});
