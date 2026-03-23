// Cursor personalizado (solo desktop)
      const cursor = document.getElementById("cursor");
      const cursorRing = document.getElementById("cursor-ring");

      if (cursor && window.matchMedia("(pointer: fine)").matches) {
        let mouseX = 0,
          mouseY = 0,
          ringX = 0,
          ringY = 0;

        document.addEventListener("mousemove", (e) => {
          mouseX = e.clientX;
          mouseY = e.clientY;
          cursor.style.left = mouseX + "px";
          cursor.style.top = mouseY + "px";
        });

        (function animateRing() {
          ringX += (mouseX - ringX) * 0.12;
          ringY += (mouseY - ringY) * 0.12;
          cursorRing.style.left = ringX + "px";
          cursorRing.style.top = ringY + "px";
          requestAnimationFrame(animateRing);
        })();
      }

      //Scroll reveal
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("visible");
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.12, rootMargin: "0px 0px -40px 0px" },
      );

      document
        .querySelectorAll(".reveal")
        .forEach((el) => observer.observe(el));

      //Navbar: sombra al hacer scroll
      const navbar = document.querySelector(".navbar");
      window.addEventListener(
        "scroll",
        () => {
          navbar.style.boxShadow =
            window.scrollY > 20 ? "0 4px 24px rgba(0,0,0,0.5)" : "none";
        },
        { passive: true },
      );