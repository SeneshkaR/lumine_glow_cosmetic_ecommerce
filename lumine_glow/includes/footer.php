<footer class="site-footer">
    <div>
        <a class="logo" href="index.php">Luminé <span>Glow</span></a>
        <p>Beauty essentials for your everyday glow.</p>
    </div>
    <div class="footer-links">
        <a href="products.php">Shop</a>
        <a href="quiz.php">Beauty Quiz</a>
        <a href="cart.php">Shopping Bag</a>
        <?php if (isLoggedIn()): ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </div>
    <p class="copyright">© <?= date('Y') ?> Luminé Glow. Academic project.</p>
</footer>
<script src="assets/js/script.js"></script>
</body>
</html>