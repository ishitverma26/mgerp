    </div>
<?php if (empty($embedMode)): ?>
  </div>
</div>
<?php endif; ?>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $script): ?>
<script src="<?= APP_URL . $script ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
