<?php
require_once 'init.php';
$page = 'index';
?>
<?php include 'header.php' ?>
<?php if ($currentUser) : ?>
  <?php
    $newFeeds = findAllPosts();
    $success = true;
    if (isset($_POST['content'])) {
      $content = $_POST['content'];
      $data = null;
      if (isset($_FILES['postImage'])) {
        $data = file_get_contents($_FILES['postImage']['tmp_name']);
      }
      $len = strlen($content);
      if ($len == 0 || $len > 1024) {
        $success = false;
      } else {
        createPost($currentUser['id'], $content, $data);
        header('Location: index.php');
        exit();
      }
    }
    ?>
    <div class="inner">
  <?php if (!$success) : ?>
    <div class="alert alert-danger" role="alert">
      Nội dung không được rỗng và dài quá 1024 ký tự!
    </div>
  <?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <textarea class="form-control" id="content" name="content" rows="3" placeholder="<?php echo $currentUser['displayName'] ?> ơi, bạn đang nghĩ gì vậy?"></textarea>
    </div>
    <div class="d-flex justify-content-between align-items-center">
    <div class="upload-btn-wrapper">
      <button class="btn">🖼️ <strong>Ảnh/Video</strong></button>
      <input type="file" id="postImage" name="postImage" />
    </div>
    <div class="select-privacy" style="width:260px;">
      <select>
        <option value="1">Công khai</option>
        <option value="2">Bạn bè</option>
        <option value="3">Chỉ mình tôi</option>
      </select>
    </div>
    <button type="submit" class="btn btn-success">Cập nhật trạng thái</button>
    </div>
  </form>
  <?php foreach ($newFeeds as $post) : ?>
    <?php $userPost = findUserById($post['userId']); ?>
    <div class="container-fluid">
      <div class="row">
        <div class="col-12 mt-3">         
          <div class="card">
            <div class="card-horizontal">
              <div class="img-square-wrapper">
                <img class="rounded-circle" style="float: left;width:100px;height:100px;" src="<?php echo empty($userPost['avatarImage']) ? './assets/images/default-avatar.jpg' : 'view-image.php?userId=' . $post['userId'] ?>" alt="<?php echo $userPost['displayName'] ?>">
              </div>
              <div style="margin-left:100px;" class="card-body">
                <h4 class="card-title"><?php echo $post['displayName']; ?>&nbsp;<img src='https://i.imgur.com/l63JR5Q.png' title=' Verified profile ' width='20' /></h4>
                <small class="text-muted">Đăng lúc: <?php echo $post['createdAt']; ?></small>
                <p class="card-text"><?php echo $post['content']; ?></p>
                <?php if ($post['image'] != NULL) : ?>
                  <figure>
                    <img src="view-image.php?postId=<?php echo $post['id'] ?>" alt="<?php echo $post['id'] ?>" class="img-fluid">
                  </figure>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>  
<?php else : ?>
  <div class="jumbotron">
    <section class="hero-section spad">
      <div class="container-fluid">
        <div class="row">
          <div class="col-xl-10 offset-xl-1">
            <div class="row">
              <div class="col-lg-7">
                <div class="hero-info">
                  <h5><strong>Yolo</strong> - Mạng xã hội dành cho sinh viên</h5>
                  <div>
                    <p>Miễn phí mà. Tham gia đi chờ chi...</p>
                    <p>
                      <a href="./register.php" class="btn btn-light" role="button">Đăng ký</a>
                      <a href="./login.php" class="btn btn-light" role="button">Đăng nhập</a>
                    </p>
                  </div>
                </div>
              </div>
              <div class="col-lg-5">
                <figure class="hero-image">
                  <img src="./assets/images/gif1.gif" alt="" class="img-fluid" alt="Responsive image">
                </figure>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
<?php endif; ?>
<?php include 'footer.php' ?>