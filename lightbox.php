<!-- --- Global Image Lightbox --- -->
<div id="image-lightbox" class="image-lightbox">
    <span class="lightbox-close">&times;</span>
    <img class="lightbox-content" id="lightbox-image-content">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lightbox = document.getElementById('image-lightbox');
    if (!lightbox) return; // Exit if lightbox not on page

    const lightboxImage = document.getElementById('lightbox-image-content');
    const closeBtn = lightbox.querySelector('.lightbox-close');

    // Function to open lightbox
    function openLightbox(e) {
        const trigger = e.currentTarget;
        const image = trigger.querySelector('img');
        if (image) {
            lightboxImage.src = image.src;
            lightbox.classList.add('show');
        }
    }

    // Function to close lightbox
    function closeLightbox() {
        lightbox.classList.remove('show');
    }

    // Attach events to all triggers dynamically
    document.body.addEventListener('click', function(e) {
        const trigger = e.target.closest('.js-lightbox-trigger');
        if (trigger) {
            openLightbox({currentTarget: trigger});
        }
    });
    
    // Close events
    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightbox.classList.contains('show')) {
            closeLightbox();
        }
    });
});
</script> 