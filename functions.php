<?php
// Load Composer's autoloader
require 'vendor/autoload.php';

// Load config
require_once 'config.php';

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$enableSendNotification = true;

function findUserByEmail($email)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute(array($email));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function findUserById($id)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute(array($id));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function sendEmail($to, $name, $subject, $content, $emailName)
{
  global $EMAIL_DOMAINNAME, $EMAIL_USERNAME, $EMAIL_PASSWORD;

  // Instantiation and passing `true` enables exceptions
  $mail = new PHPMailer(true);

  //Server settings
  $mail->isSMTP();                                            // Send using SMTP
  $mail->CharSet    = 'UTF-8';
  $mail->Host       = 'smtp.gmail.com';                    // Set the SMTP server to send through
  $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
  $mail->Username   = $EMAIL_USERNAME;                     // SMTP username
  $mail->Password   = $EMAIL_PASSWORD;                               // SMTP password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
  $mail->Port       = 587;                                    // TCP port to connect to

  //Recipients
  $mail->setFrom($EMAIL_USERNAME, $EMAIL_DOMAINNAME . " - $emailName");
  $mail->addAddress($to, $name);     // Add a recipient

  // Content
  $mail->isHTML(true);                                  // Set email format to HTML
  $mail->Subject = $subject;
  $mail->Body    = $content;
  $mail->AltBody = $content;

  $mail->send();
  return true;
}

function createUser($displayName, $email, $password, $avatar, $background)
{
  global $db, $BASE_URL;
  $hashPassword = password_hash($password, PASSWORD_DEFAULT);
  $code = generateRandomString(16);
  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d");

  $stmt = $db->prepare("INSERT INTO users (displayName, email, password, avatarImage, backgroundImage, status, code, createdDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute(array($displayName, $email, $hashPassword, $avatar, $background, 0, $code, $dateNow));
  $id = $db->lastInsertId();

  $bodyContent = "Mã kích hoạt tài khoản của bạn là <strong>$code</strong><br />"
    . "Hoặc click vào <a href='$BASE_URL/activate.php?code=$code' target='_blank'>ĐÂY</a> để kích hoạt!";
  sendEmail($email, $displayName, 'Kích hoạt tài khoản', $bodyContent, 'Xác nhận mã');
  return $id;
}

function updateLoginTime($userId)
{
  global $db;
  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("UPDATE users SET lastLogin=? WHERE id=?");
  return $stmt->execute(array($dateNow, $userId));
}

function generateRandomString($length = 10)
{
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}

function activateUser($code)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE code=? AND status=?");
  $stmt->execute(array($code, 0));
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user && $user['code'] == $code) {
    $stmt = $db->prepare("UPDATE users SET code=?, status=? WHERE id=?");
    $stmt->execute(array('', 1, $user['id']));
    return true;
  }
  return false;
}

function sendCodeResetPassword($user)
{
  global $db, $BASE_URL;
  $code = generateRandomString(16);

  $stmt = $db->prepare("UPDATE users SET code=? WHERE id=?");
  $stmt->execute(array($code, $user['id']));

  $bodyContent = "Mã reset mật khẩu của bạn là <strong>$code</strong><br />"
    . "Hoặc click vào <a href='$BASE_URL/reset-password.php?code=$code' target='_blank'>ĐÂY</a> để reset mật khẩu!";
  sendEmail($user['email'], $user['displayName'], 'Cài lại mật khẩu', $bodyContent, 'Xác nhận mã');
}

function checkValidCodeResetPassword($code)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE code=? AND status=?");
  $stmt->execute(array($code, 1));
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user) {
    return true;
  }
  return false;
}

function resetPassword($code, $password)
{
  global $db;
  $check = checkValidCodeResetPassword($code);
  if ($check) {
    $hashPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET code=?, password=? WHERE code=?");
    $stmt->execute(array('', $hashPassword, $code));
    return true;
  }
  return false;
}

function updateUserProfile($id, $displayName, $phoneNumber, $avatarImage, $yearOfBirth, $nickName, $introContent, $backgroundImage)
{
  global $db;
  $stmt = $db->prepare("UPDATE users SET displayName=?, phoneNumber=?, avatarImage=?, yearOfBirth=?, nickName=?, introContent=?, backgroundImage=? WHERE id=?");
  return $stmt->execute(array($displayName, $phoneNumber, $avatarImage, $yearOfBirth, $nickName, $introContent, $backgroundImage, $id));
}

function getNewFeeds()
{
  global $db;
  $stmt = $db->prepare("SELECT p.*, u.displayName FROM posts as p join users u on p.userId = u.id ORDER BY p.createdAt DESC");
  $stmt->execute();
  $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return $posts;
}

function getAvatarImage($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT avatarImage FROM users WHERE id = ?");
  $stmt->execute(array($userId));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPostImage($postId)
{
  global $db;
  $stmt = $db->prepare("SELECT image FROM posts WHERE id = ?");
  $stmt->execute(array($postId));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

//POST AREA
// Function create post
function createPost($userID, $Content, $image, $role)
{
  global $db;
  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("INSERT INTO posts (userId, createdAt, content, image, role) VALUES (?, ?, ?, ?,?)");
  $stmt->execute(array($userID, $dateNow, $Content, $image, $role));
  return $db->lastInsertId();
}

function findAllPosts($userId, $page)
{
  global $limitPaging;
  global $db;

  $usr = $userId;

  $stmt = $db->prepare("SELECT  p.*,u.displayName,u.id as myImageID ,p.createdAt from ( SELECT * from posts where userId = ? union (select * from posts where userId in (select followingId from follows where followerId = ?) and role=1)
  union (select * from posts where userId in (select f.followingId from follows f where followerId = ? and exists (select * from friendship where (userId1=? and userId2=f.followingId) or (userId2=? and userId1=f.followingId))) and role=2)  
  ) p join users u on (p.userId = u.id) ORDER BY p.createdAt DESC LIMIT ". $limitPaging*$page);
  $stmt->execute(array($userId, $userId, $userId, $userId, $userId)); 
  $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return $posts;
}

function postsById($userId, $page)
{
  global $limitPaging;
  global $db;
  $stmt = $db->prepare("SELECT p.*,u.displayName,u.id as myImageID ,p.createdAt FROM posts as p left join users as u on p.userId = u.id WHERE p.userId = ? ORDER BY p.createdAt DESC LIMIT ". ($page*$limitPaging));
  $stmt->execute(array($userId));
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//POST PRIVACY
// Change privacy
function changePostPrivacy($postId, $userId, $role)
{
  global $db;
  $stmt = $db->prepare("UPDATE posts SET role=? WHERE id=? AND userId=?");
  return $stmt->execute(array($role, $postId, $userId));
}

//Public Mode
function postsPublic($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT p.*,u.displayName,u.id as myImageID ,p.createdAt FROM posts as p left join users as u on p.userId = u.id WHERE p.userId = ? and p.role = 1 ORDER BY p.createdAt DESC");
  $stmt->execute(array($userId));
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Friend Mode
function postsFriend($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT p.*,u.displayName,u.id as myImageID ,p.createdAt FROM posts as p left join users as u on p.userId = u.id WHERE p.userId = ? and p.role = 2 ORDER BY p.createdAt DESC");
  $stmt->execute(array($userId));
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
//Show post with relationship.
function showPosts($userId1, $userId2, $page)
{
  global $limitPaging;

  if ($userId1 == $userId2) {
    return postsById($userId1, $page);
  }
  $user1 = findUserById($userId1);
  $user2 = findUserById($userId2);
  if ($user1['id'] != $userId1 || $user2['id'] != $userId2) {
    return "ID không tồn tại !";
  }
  $isFollowing  = getFriendShip($userId2, $userId1);
  $isFollower = getFriendShip($userId1, $userId2);
  $arrPosts = [];

  if ($isFollower && $isFollowing) {
    $pstFriend = postsFriend($userId2);
    $pstPublic = postsPublic($userId2);
    $arrPosts = array_merge($arrPosts, $pstFriend);
    $arrPosts = array_merge($arrPosts, $pstPublic);
  }
  if (($isFollower && !$isFollowing) || (!$isFollower && $isFollowing) || (!$isFollower && !$isFollowing)) {
    $pstPublic = postsPublic($userId2);
    $arrPosts = array_merge($arrPosts, $pstPublic);
  }
  return array_slice($arrPosts, 0, $limitPaging*$page);
}

//FRIEND AREA
// REMOVE - ADD FRIEND
function sendFriendRequest($userId1, $userId2)
{
  global $db, $enableSendNotification, $BASE_URL;

  $isFollower = getFriendShip($userId2, $userId1);

  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("INSERT INTO friendship SET userId1=?, userId2=?, createdAt=?");
  $execute = $stmt->execute(array($userId1, $userId2, $dateNow));

  if ($enableSendNotification) {
    // Send email notification to userId2
    $user1 = findUserById($userId1);
    $user2 = findUserById($userId2);
    $user1Link = "<a href='$BASE_URL/profile.php?id=$userId1'>{$user1['displayName']}</a>";
    $user2Link = "<a href='$BASE_URL/profile.php?id=$userId2'>{$user2['displayName']}</a>";
    if ($execute && !$isFollower) {
      $friendLink = "<a href='$BASE_URL/friend-request.php'>ĐÂY</a>";
      $bodyContent = "Bạn vừa được $user1Link gửi một lời mời kết bạn! <br />
                  Click vào $friendLink để đi tới thông báo kết bạn!";

      sendEmail($user2['email'], $user2['displayName'], 'Bạn có một lời mời kết bạn', $bodyContent, 'Kết bạn');
    } else {
      $bodyContent = "Chúc mừng! Bạn và $user2Link vừa trở thành bạn bè!";
      sendEmail($user1['email'], $user1['displayName'], 'Trở thành bạn bè', $bodyContent, 'Kết bạn');
      $bodyContent = "Chúc mừng! Bạn và $user1Link vừa trở thành bạn bè!";
      sendEmail($user2['email'], $user2['displayName'], 'Trở thành bạn bè', $bodyContent, 'Kết bạn');
    }
  }

  return $execute;
}
function removeFriendRequest($userId1, $userId2)
{
  global $db, $enableSendNotification, $BASE_URL;

  $isFollowing = getFriendShip($userId1, $userId2);
  $isFollower = getFriendShip($userId2, $userId1);

  $stmt = $db->prepare("DELETE FROM friendship WHERE (userId1=? AND userId2=?) OR (userId1=? AND userId2=?)");
  $execute = $stmt->execute(array($userId1, $userId2, $userId2, $userId1));

  if ($enableSendNotification) {
    // Send email notification to userId2
    if ($execute && $isFollower && $isFollowing) {
      $user1 = findUserById($userId1);
      $user2 = findUserById($userId2);

      $user1Link = "<a href='$BASE_URL/profile.php?id=$userId1'>{$user1['displayName']}</a>";
      $bodyContent = "Bạn vừa mất một người bạn: $user1Link!";

      sendEmail($user2['email'], $user2['displayName'], 'Bạn vừa mất một người bạn', $bodyContent, 'Bạn bè');
    }
  }

  return $execute;
}
function getFriendShip($userId1, $userId2)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM friendship WHERE userId1=? AND userId2=?");
  $stmt->execute(array($userId1, $userId2));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getFriendRequest($userId)
{
  global $db;

  // Get users list
  $stmt = $db->prepare("SELECT * FROM friendship WHERE userid1=? OR userId2=?");
  $stmt->execute(array($userId, $userId));
  $friendships = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Get friend requests
  $friendRequest = array();
  foreach ($friendships as $friendship) {
    if ($friendship['userId1'] == $userId) {
      $isFollower = getFriendShip($friendship['userId2'], $userId);
      if (!$isFollower) {
        $user = findUserById($friendship['userId2']);
        array_push($friendRequest, array($user, 'isFollowing'));
      }
    }
    if ($friendship['userId2'] == $userId) {
      $isFollowing = getFriendShip($userId, $friendship['userId1']);
      if (!$isFollowing) {
        $user = findUserById($friendship['userId1']);
        array_push($friendRequest, array($user, 'isFollower'));
      }
    }
  }

  return $friendRequest;
}

// COMMENT AREA

function addComment($postId, $userId, $content)
{
  global $db;
  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("INSERT INTO comments (postId,	userId,	content, createdAt) VALUES (?, ?, ?, ?)");
  $stmt->execute(array($postId, $userId, $content, $dateNow));
  return $db->lastInsertId();
}

function commentWithPostId($postId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM comments WHERE postId = ? ORDER BY createdAt ASC");
  $stmt->execute(array($postId));
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addLike($userLikeId, $postId)
{
  global $db;
  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("INSERT INTO likes (userLikeId, postId, likeAt) VALUES (?, ?, ?)");
  $stmt->execute(array($userLikeId, $postId, $dateNow));
  return $db->lastInsertId();
}

function removeLike($currentUser, $postId)
{
  global $db;
  $stmt = $db->prepare("DELETE FROM likes WHERE userLikeId=? and postId=?");
  $stmt->execute(array($currentUser, $postId));
  return $stmt->execute(array($currentUser, $postId));
}

function wasLike($currentUser, $postId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM likes WHERE userLikeId=? and postId=?");
  $stmt->execute(array($currentUser, $postId));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function countLike($postId)
{
  global $db;
  $stmt = $db->prepare("SELECT COUNT(*) as count FROM likes WHERE postId=?");
  $stmt->execute(array($postId));
  $rows = $stmt->fetch(PDO::FETCH_ASSOC);
  return $rows['count'];
}
// MESSAGE AREA

function getLatestConversations($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT toUserId AS id, u.displayName, u.avatarImage FROM messages AS m LEFT JOIN users AS u ON u.id = m.toUserId WHERE fromUserId = ? GROUP BY toUserId ORDER BY createdAt DESC");
  $stmt->execute(array($userId));
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
  for ($i = 0; $i < count($result); $i++) {
    $stmt = $db->prepare("SELECT * FROM messages WHERE fromUserId = ? AND toUserId = ? ORDER BY createdAt DESC LIMIT 1");
    $stmt->execute(array($userId, $result[$i]['id']));
    $lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    $result[$i]['lastMessage'] = $lastMessage;
  }
  return $result;
}

function getMessagesWithUserId($fromUserId, $toUserId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM messages WHERE fromUserId = ? AND toUserId = ? ORDER BY createdAt");
  $stmt->execute(array($fromUserId, $toUserId));
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sendMessage($userId1, $userId2, $content)
{
  global $db, $enableSendNotification, $BASE_URL;

  $stmt = $db->prepare("SELECT * FROM messages WHERE fromUserId = ? AND toUserId = ?");
  $stmt->execute(array($userId1, $userId2));
  $check = $stmt->fetchAll(PDO::FETCH_ASSOC);

  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("INSERT INTO messages (fromUserId, toUserId, content, type, createdAt) VALUE (?, ?, ?, ?, ?)");
  $stmt->execute(array($userId1, $userId2, $content, 0, $dateNow));

  $id = $db->lastInsertId();
  $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
  $stmt->execute(array($id));

  $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);

  $stmt = $db->prepare("INSERT INTO messages (toUserId, fromUserId, content, type, createdAt) VALUE (?, ?, ?, ?, ?)");
  $stmt->execute(array($userId1, $userId2, $content, 1, $newMessage['createdAt']));


  $options = array(
    'cluster' => 'ap1',
    'useTLS' => true
  );
  $pusher = new Pusher\Pusher(
    '2d930b15cfe94002c0c7',
    '6d0b5d9e853b36b66568',
    '922524',
    $options
  );

  
  $data['message'] = 'hello world';
  $data['userSend'] = $userId1;
  $pusher->trigger('messenger', 'newMessage', $data);

  if ($enableSendNotification && count($check) == 0) {
    // Send email notification to userId2
    $user1 = findUserById($userId1);
    $user2 = findUserById($userId2);

    $message = strlen($newMessage['content']) > 30 ? substr($newMessage['content'], 0, 30) . "..." : $newMessage['content'];
    $linkMessage = "<a href='$BASE_URL/messages.php'>ĐÂY</a>";
    $user1Link = "<a href='$BASE_URL/profile.php?id=$userId1'>{$user1['displayName']}</a>";
    $bodyContent = "Bạn vừa mới nhận được một tin nhắn mới từ người bạn: $user1Link! <br />
                    <b>Thông tin tin nhắn:</b> $message <br />
                    <b>Tin nhắn được gửi vào lúc:</b> {$newMessage['createdAt']} <br />
                    Click vào $linkMessage để đi tới phần tin nhắn!";

    sendEmail($user2['email'], $user2['displayName'], 'Tin nhắn mới', $bodyContent, 'Tin nhắn');
  }
}

function deleteMessage($messageId)
{
  global $db;
  $stmt = $db->prepare("DELETE FROM messages WHERE id=?");
  return $stmt->execute(array($messageId));
}

//LINH: Custom Func get friend for message (Shouldn't change)
function getFriends($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM friendship WHERE userId1 = ? OR userId2 = ?");
  $stmt->execute(array($userId, $userId));
  $followings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $friends = array();
  for ($i = 0; $i < count($followings); $i++) {
    $row1 = $followings[$i];
    if ($userId == $row1['userId1']) {
      $userId2 = $row1['userId2'];
      for ($j = 0; $j < count($followings); $j++) {
        $row2 = $followings[$j];
        if ($userId == $row2['userId2'] && $userId2 == $row2['userId1']) {
          $friends[] = findUserById($userId2);
        }
      }
    }
  }
  return $friends;
}

function getFriendNotFollowing($userId) {
  global $db;
  $stmt = $db->prepare("SELECT u.* FROM friendship as f join users as u WHERE f.userId1 = ? and f.userId2 = u.id");
  $stmt->execute(array($userId));

  $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return $friends;
}
// FOLLOW AREA
function addFollow($followerId, $followingUserId)
{
  global $db;
  date_default_timezone_set("Asia/Ho_Chi_Minh");
  $dateNow = date("Y-m-d H:i:s");
  $stmt = $db->prepare("INSERT INTO follows (followerId, followingId, followAt) VALUES (?, ?, ?)");
  $stmt->execute(array($followerId, $followingUserId, $dateNow));
  return $db->lastInsertId();
}

function removeFollow($followerId, $followingUserId)
{
  global $db;
  $stmt = $db->prepare("DELETE FROM follows WHERE followerId=? and followingId=?");
  $stmt->execute(array($followerId, $followingUserId));
  return $stmt->execute(array($followerId, $followingUserId));
}

function wasFollow($currentUser, $followingUserId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM follows WHERE followerId=? and followingId=?");
  $stmt->execute(array($currentUser, $followingUserId));
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

// SEARCH AREA
function searchUserByName($name)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE displayName LIKE ? LIMIT 100");
  $stmt->execute(array('%'.$name.'%'));
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}