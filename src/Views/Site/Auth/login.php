<?php
if (isset($arguments)) {
  if (isset($arguments['errors'])) {
    $Error = $arguments['errors'];
  }
  if (isset($arguments['success'])) {
    $Success = $arguments['success'];
  }
}

$CSRF = $_SESSION['User']->CSRF->Token;

ob_start();
?>
<div class="container">
  <div class="row">
    <div class="col-md-12">
      <h1 class="text-center"></h1>
    </div>
  </div>
<link href="<?= Registry::get("RouteTranslations")['CSSPath']?>/signin.css" rel="stylesheet">
<form class="form-signin" action="<?=Registry::get("RouteTranslations")['LoginPage'];?>" method="POST" enctype="multipart/form-data">
  <img class="mb-4" src="https://getbootstrap.com/docs/4.0/assets/brand/bootstrap-solid.svg" alt="" width="72"
    height="72">
  <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
  <label for="username" class="sr-only">Username</label>
  <input type="text" name="username" id="username" class="form-control" placeholder="Username" required autofocus>
  <label for="password" class="sr-only">Password</label>
  <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
  <input type="hidden" name="csrf" value="<?= $CSRF ?>">
  <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
  <p class="mt-5 mb-3 text-muted">&copy; Kieron 2024</p>

  <?php if (isset($Error)): ?>
    <div class="alert alert-warning" role="alert">
      <?= $Error ?>
    </div>
  <?php endif; ?>

  <?php if (isset($Success)): ?>
    <div class="alert alert-success" role="alert">
      <?= $Success ?>
    </div>
  <?php endif; ?>

</form>

<?php
$template = ob_get_contents();
ob_end_clean();
?>