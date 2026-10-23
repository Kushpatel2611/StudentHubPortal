document.addEventListener('DOMContentLoaded', () => {
  const slider = document.querySelector('.slider');

  if (!slider) {
    return;
  }

  const slides = Array.from(slider.querySelectorAll('.slide'));
  const dots = Array.from(slider.querySelectorAll('.slider-dot'));
  const prevButton = slider.querySelector('[data-direction="prev"]');
  const nextButton = slider.querySelector('[data-direction="next"]');
  let currentIndex = 0;
  let autoplayId = null;

  if (slides.length === 0) {
    return;
  }

  const updateSlider = (newIndex) => {
    currentIndex = (newIndex + slides.length) % slides.length;

    slides.forEach((slide, index) => {
      const isActive = index === currentIndex;
      slide.classList.toggle('is-active', isActive);
      slide.setAttribute('aria-hidden', String(!isActive));
    });

    dots.forEach((dot, index) => {
      const isActive = index === currentIndex;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-selected', String(isActive));
      dot.tabIndex = isActive ? 0 : -1;
    });
  };

  const startAutoplay = () => {
    clearInterval(autoplayId);
    autoplayId = setInterval(() => {
      updateSlider(currentIndex + 1);
    }, 4000);
  };

  const stopAutoplay = () => {
    clearInterval(autoplayId);
  };

  prevButton?.addEventListener('click', () => {
    updateSlider(currentIndex - 1);
    startAutoplay();
  });

  nextButton?.addEventListener('click', () => {
    updateSlider(currentIndex + 1);
    startAutoplay();
  });

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      updateSlider(index);
      startAutoplay();
    });
  });

  slider.addEventListener('mouseenter', stopAutoplay);
  slider.addEventListener('mouseleave', startAutoplay);
  slider.addEventListener('focusin', stopAutoplay);
  slider.addEventListener('focusout', startAutoplay);

  updateSlider(0);
  startAutoplay();
});
