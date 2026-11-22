// Simple slideshow: changes picture every 4 seconds, with arrows and dots.
document.addEventListener('DOMContentLoaded', function () {

    var slides = document.querySelectorAll('#slideshow .slide');
    var dotsBox = document.getElementById('dots');
    var current = 0;
    var timer;

    // make one dot for each slide
    slides.forEach(function (slide, i) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'dot';
        dot.addEventListener('click', function () {
            showSlide(i);
            restartTimer();
        });
        dotsBox.appendChild(dot);
    });
    var dots = dotsBox.querySelectorAll('.dot');

    // show the slide with this number
    function showSlide(n) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');
        current = (n + slides.length) % slides.length;   // loops around
        slides[current].classList.add('active');
        dots[current].classList.add('active');
    }

    // start the automatic change
    function startTimer() {
        timer = setInterval(function () {
            showSlide(current + 1);
        }, 4000);
    }

    function restartTimer() {
        clearInterval(timer);
        startTimer();
    }

    document.getElementById('nextBtn').addEventListener('click', function () {
        showSlide(current + 1);
        restartTimer();
    });
    document.getElementById('prevBtn').addEventListener('click', function () {
        showSlide(current - 1);
        restartTimer();
    });

    showSlide(0);
    startTimer();

    // open and close the menu on phones
    var menuToggle = document.getElementById('menuToggle');
    var menuList = document.getElementById('menuList');
    menuToggle.addEventListener('click', function () {
        menuList.classList.toggle('open');
    });

    // back to top button: shows after scrolling down a little
    var toTop = document.getElementById('toTop');
    window.addEventListener('scroll', function () {
        toTop.classList.toggle('show', window.scrollY > 300);
    });
    toTop.addEventListener('click', function (e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
