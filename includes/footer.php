
<style>
@import url("https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css");

.footer {
    background: #15151e;
    color: #ccc;
    padding-top: 2rem;
    padding-bottom: 2rem;
    letter-spacing: 2px;
    position: relative;
}

.footer a,
.footer a:visited,
.footer a:active,
.footer a:focus {
    color: #d086ff !important;
}

.footer .text-decoration-none,
.footer a.text-decoration-none {
    color: #d086ff !important;
}

.footer a:hover {
    color: #e2a9ff !important;
    text-decoration: underline;
}

.footer::before {
  content: '';
  position: absolute;
  top: -1px;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, 
    transparent,
    rgba(127, 0, 255, 0.3) 20%,
    rgba(127, 0, 255, 0.5) 50%,
    rgba(127, 0, 255, 0.3) 80%,
    transparent
  );
}

.footer-credit {
  margin-top: 0.9rem;
  padding-top: 0.9rem;
  border-top: 1px solid rgba(178, 0, 255, 0.18);
  letter-spacing: 0.5px;
  color: #b9b9c6;
}

.footer-credit .credit-heart {
  color: #d086ff;
  vertical-align: middle;
  margin: 0 0.2rem;
}
</style>

<footer class="footer text-light">
  <div class="container text-center small">
    <p class="mb-1">&copy; <?= date('Y') ?> GameTracker. All rights reserved.</p>
    <p class="mb-1">Game images provided by <a href="https://rawg.io/apidocs" target="_blank" rel="noopener">RAWG</a> and <a href="https://api.igdb.com/" target="_blank" rel="noopener">IGDB</a> for educational and non-commercial use.</p>
    <p class="mb-0">
      <a href="privacy-policy.php" class="text-decoration-none">Privacy Policy</a> |
      <a href="roadmap.php" class="text-decoration-none">Roadmap</a> |
      <a href="terms.php" class="text-decoration-none">Terms of Service</a>
    </p>
    <p class="mb-0 footer-credit">
      Made with <i class="ph-fill ph-heart-straight credit-heart" aria-hidden="true"></i> by KyleDevs
    </p>
  </div>
</footer>

<script src="https://unpkg.com/@phosphor-icons/web"></script>
