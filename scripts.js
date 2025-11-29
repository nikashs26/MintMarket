(function () {
  'use strict';

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCarousel);
  } else {
    initCarousel();
  }

  function initCarousel() {
    var slides = document.querySelectorAll('.slide');
    var dots = document.querySelectorAll('.page-footer__dots .dot');
    
    if (!slides.length || !dots.length) {
      console.warn('Slider: Missing slides or dots');
      return;
    }

    console.log('Slider initialized with', slides.length, 'slides');

    var currentIndex = 0;
    var intervalMs = 7000;
    var timerId = null;

    function showSlide(index) {
      if (index < 0 || index >= slides.length) {
        index = 0;
      }
      currentIndex = index;

      // Hide all slides first
      for (var i = 0; i < slides.length; i++) {
        slides[i].classList.remove('slide--active');
      }

      // Show only the active slide
      if (slides[index]) {
        slides[index].classList.add('slide--active');
        var title = slides[index].querySelector('.gallery__title');
        if (title) {
          console.log('Showing slide', index, ':', title.textContent.trim());
        }
      }

      // Update dot indicators
      for (var j = 0; j < dots.length; j++) {
        if (j === index) {
          dots[j].classList.add('dot--active');
        } else {
          dots[j].classList.remove('dot--active');
        }
      }
    }

    function nextSlide() {
      var nextIndex = currentIndex + 1;
      if (nextIndex >= slides.length) {
        nextIndex = 0;
      }
      showSlide(nextIndex);
    }

    function startAutoAdvance() {
      if (timerId !== null) {
        clearInterval(timerId);
      }
      timerId = window.setInterval(nextSlide, intervalMs);
    }

    function resetAutoAdvance() {
      if (timerId !== null) {
        window.clearInterval(timerId);
        timerId = null;
      }
      startAutoAdvance();
    }

    // Add click handlers to dots
    for (var k = 0; k < dots.length; k++) {
      (function (dotIndex) {
        dots[dotIndex].addEventListener('click', function () {
          showSlide(dotIndex);
          resetAutoAdvance();
        });
      })(k);
    }

    // Initialize: show first slide and start auto-advance
    showSlide(0);
    startAutoAdvance();
  }
})();
