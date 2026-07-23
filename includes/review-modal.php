<?php if (!defined('BASE_URL')) { define('BASE_URL', '/1hnd/gametracker/'); } ?>
<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Write a Review</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reviewForm">
                    <input type="hidden" id="reviewGameId" name="game_id">
                    
                    <!-- Rating Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Rate this game</label>
                        <div class="rating-stars mb-2">
                            <div class="star-container" style="direction: ltr;">
                                <!-- 5 stars, each can be half -->
                                <i class="bi bi-star star" data-value="1"></i>
                                <i class="bi bi-star star" data-value="2"></i>
                                <i class="bi bi-star star" data-value="3"></i>
                                <i class="bi bi-star star" data-value="4"></i>
                                <i class="bi bi-star star" data-value="5"></i>
                            </div>
                            <div class="rating-text mt-2">
                                <span id="ratingValue">0</span>/5
                            </div>
                        </div>
                    </div>
                    
                    <!-- Review Title -->
                    <div class="mb-3">
                        <label for="reviewTitle" class="form-label">Review Title (optional)</label>
                        <input type="text" class="form-control" id="reviewTitle" name="review_title" placeholder="Summarize your thoughts...">
                    </div>
                    
                    <!-- Review Text -->
                    <div class="mb-3">
                        <label for="reviewText" class="form-label">Your Review (optional)</label>
                        <textarea class="form-control" id="reviewText" name="review_text" rows="5" placeholder="Share your thoughts about this game..."></textarea>
                    </div>
                    
                    <!-- Privacy Setting -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isPublic" name="is_public" checked>
                            <label class="form-check-label" for="isPublic">
                                Make this review public
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitReview">Submit Review</button>
            </div>
        </div>
    </div>
</div>

<!-- Review Feedback Modal -->
<div class="modal fade" id="reviewFeedbackModal" tabindex="-1" aria-labelledby="reviewFeedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewFeedbackModalLabel">Review</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="reviewFeedbackMessage">
        <!-- Message will be inserted here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<style>
.rating-stars {
    text-align: center;
}

.star-container {
    display: inline-flex;
    gap: 2px;
}

.star {
    font-size: 2rem;
    color: #6c757d;
    cursor: pointer;
    transition: color 0.2s ease;
}

.star:hover,
.star.active {
    color: #b200ff;
}

.star.filled {
    color: #b200ff;
}

.rating-text {
    font-size: 1.1rem;
    font-weight: bold;
    color: #b200ff;
}

/* Modal styling */
.modal-content {
    background: #1e1e2f;
    border: 1px solid rgba(127, 0, 255, 0.2);
}

.modal-header {
    border-bottom: 1px solid rgba(127, 0, 255, 0.2);
}

.modal-footer {
    border-top: 1px solid rgba(127, 0, 255, 0.2);
}

.form-control {
    background: rgba(30, 30, 47, 0.8);
    border: 1px solid rgba(127, 0, 255, 0.2);
    color: #fff;
}

.form-control:focus {
    background: rgba(30, 30, 47, 0.9);
    border-color: #b200ff;
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(178, 0, 255, 0.25);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.rating-stars .bi-star-fill,
.rating-stars .bi-star-half {
  color: #b200ff !important;
}
.rating-stars .bi-star {
  color: #6c757d;
}
.rating-stars .bi-star-half,
.rating-display .bi-star-half {
  color: #b200ff !important;
  fill: #b200ff !important;
}
.rating-stars .bi-star-half svg,
.rating-display .bi-star-half svg {
  fill: #b200ff !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedRating = 0;
    let currentGameId = null;
    const stars = document.querySelectorAll('.star');
    const ratingValue = document.getElementById('ratingValue');

    // Helper to get mouse position (left or right half)
    function getStarValue(e, star) {
        const rect = star.getBoundingClientRect();
        const x = e.clientX - rect.left;
        return x < rect.width / 2 ? 0.5 : 1;
    }

    stars.forEach((star, idx) => {
        // Click: set rating
        star.addEventListener('click', function(e) {
            const base = parseInt(star.getAttribute('data-value'));
            const add = getStarValue(e, star);
            selectedRating = base - 1 + add;
            updateStars(selectedRating);
            ratingValue.textContent = selectedRating;
        });
        // Hover: preview rating
        star.addEventListener('mousemove', function(e) {
            const base = parseInt(star.getAttribute('data-value'));
            const add = getStarValue(e, star);
            updateStars(base - 1 + add);
        });
        // Mouseout: reset preview
        star.addEventListener('mouseleave', function() {
            updateStars(selectedRating);
        });
    });

    function updateStars(rating) {
        stars.forEach((star, i) => {
            const starNum = i + 1;
            star.classList.remove('bi-star-fill', 'bi-star-half', 'bi-star');
            if (rating >= starNum) {
                star.classList.add('bi-star-fill');
            } else if (rating >= starNum - 0.5) {
                star.classList.add('bi-star-half');
            } else {
                star.classList.add('bi-star');
            }
        });
    }
    
    // Review modal functionality
    window.openReviewModal = function(gameId, existingReview = null) {
        currentGameId = gameId;
        document.getElementById('reviewGameId').value = gameId;
        
        // Reset form
        document.getElementById('reviewForm').reset();
        selectedRating = 0;
        updateStars(0);
        document.getElementById('ratingValue').textContent = '0';
        
        // Populate with existing review if editing
        if (existingReview) {
            selectedRating = existingReview.rating;
            updateStars(existingReview.rating);
            document.getElementById('ratingValue').textContent = existingReview.rating;
            document.getElementById('reviewTitle').value = existingReview.review_title || '';
            document.getElementById('reviewText').value = existingReview.review_text || '';
            document.getElementById('isPublic').checked = existingReview.is_public;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
        modal.show();
    };
    
    // Submit review
    document.getElementById('submitReview').addEventListener('click', function() {
        if (selectedRating === 0) {
            showReviewFeedback('Please select a rating', false);
            return;
        }
        
        const formData = new FormData();
        formData.append('game_id', currentGameId);
        formData.append('rating', selectedRating);
        formData.append('review_title', document.getElementById('reviewTitle').value);
        formData.append('review_text', document.getElementById('reviewText').value);
        formData.append('is_public', document.getElementById('isPublic').checked ? '1' : '0');
        
        fetch('<?= BASE_URL ?>api/add-review.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
                modal.hide();
                // Show feedback modal
                showReviewFeedback('Review submitted successfully!', true);
                // Optionally reload after closing feedback modal
                document.getElementById('reviewFeedbackModal').addEventListener('hidden.bs.modal', function reloadOnClose() {
                    location.reload();
                    document.getElementById('reviewFeedbackModal').removeEventListener('hidden.bs.modal', reloadOnClose);
                });
            } else {
                showReviewFeedback('Error: ' + data.message, false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showReviewFeedback('An error occurred while submitting the review', false);
        });
    });

    // Show feedback modal
    function showReviewFeedback(message, success) {
        document.getElementById('reviewFeedbackMessage').textContent = message;
        document.getElementById('reviewFeedbackModalLabel').textContent = success ? 'Success' : 'Error';
        const feedbackModal = new bootstrap.Modal(document.getElementById('reviewFeedbackModal'));
        feedbackModal.show();
    }
});
</script> 