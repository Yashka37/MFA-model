<?php
session_start();
//error_reporting(E_ALL); //ini_set('display_errors', 1);

if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit;
}

$regstatus = $_SESSION['regstatus'] ?? '';
$logstatus = $_SESSION['logstatus'] ?? '';

if (isset($_GET['qt']) && !empty(trim($_GET['qt']))) {  // Обр QR с мобильного
    $_SESSION['qt'] = trim($_GET['qt']);
    header("Location: login.php");
    exit;
}
if(isset($_GET['q']) && $_GET['q'] == 1) {
    $qr_py = exec("python3 scripts/qr_gen.py", $output, $return);
    if ($return === 0 && !empty($output[0])) {
        $_SESSION['qt'] = trim($output[0]);
        $_SESSION['qr_image'] = "/qr/" . $_SESSION['qt'] . ".png";
    } else {
        $_SESSION['logstatus'] = "Ошибка генерации QR";
    }
}

if (isset($_GET['s']) && $_GET['s'] !== '') {
    switch ((string)$_GET['s']) {
        case '0':
            $conn = mysqli_connect("localhost", "root", "xxXX1234", "UserInfo");
            if (!$conn) {
                echo "Соединение сброшено " . mysqli_connect_error();
                header("Location: /index.html");
                exit;
            }
            
            $login = $_POST['login'];
            $pass = $_POST['pass'];
            $repeatpass = $_POST['repeatpass'];
            $url = "/login";

            if (empty($login) || empty($pass) || empty($repeatpass)) {
                $_SESSION['regstatus'] = "Заполнены не все поля";
                header("Refresh:0;$url");
                exit;
            } else {
                if ($pass != $repeatpass) {
                    $_SESSION['regstatus'] = "Пароли не совпадают";
                    header("Refresh:0;$url");
                    exit;
                } else {
                    $checklogin = "SELECT * FROM users WHERE login = '$login'";
                    $check = $conn->query($checklogin);
                    if ($check->num_rows > 0) {
                        $_SESSION['regstatus'] = "Пользователь с таким логином уже существует";
                        header("Refresh:0;$url");
                        exit;
                    } else {
                        $_SESSION['login'] = $login;
                        $hashed_pass = hash('sha256', $pass);
                        $makeuser = "INSERT INTO `users` (login, pass) VALUES ('$login', '$hashed_pass')";
                        if ($conn->query($makeuser) === TRUE) {
                            $useridsql = "SELECT id FROM users WHERE login = '$login'";
                            $sql_userid = $conn->query($useridsql);
                            if ($sql_userid->num_rows > 0) {
                                $row_userid = $sql_userid->fetch_assoc();
                                $userid = $row_userid['id'];
                                $_SESSION['userid'] = $userid;
                                $url = "/main";
                                header("Refresh:0;$url");
                                exit;
                            } else {
                                $_SESSION['regstatus'] = "Ошибка ID пользователя " . $conn->error;
                                header("Refresh:0;$url");
                                exit;
                            }
                        } 
                    }
                }
            }
            $conn->close();
            break;
            
        case '1':
            $conn = new mysqli("localhost", "root", "xxXX1234", "UserInfo");
            
            if (!empty($_POST['login']) && !empty($_POST['pass'])) {
                $hashed_pass = hash('sha256', $_POST['pass']);
                $stmt = $conn->prepare("SELECT id, login, role FROM users WHERE login = ? AND pass = ?");
                $stmt->bind_param("ss", $_POST['login'], $hashed_pass);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $_SESSION['userid'] = $user['id'];
                    $_SESSION['login'] = $user['login'];
                    $_SESSION['role'] = $user['role'];
                    
                    if (!empty($_POST['qt'])) {
                        $qr_conn = new mysqli("localhost", "root", "xxXX1234", "resourses");
                        $qr_conn->query("UPDATE qr_tokens SET status='confirmed', userid=".$user['id']." WHERE token='".$qr_conn->real_escape_string($_POST['qt'])."'");
                        $qr_conn->close();
                    }
                    
                    header("Location: /main");
                    exit;
                }
            }
            $_SESSION['logstatus'] = "Неверный логин или пароль";
            header("Location: /login");
            exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="resourse/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=0.9">
    <link rel="stylesheet" href="css/login.css">
    <title>Y37 | Вход</title>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h1 class="header">Y37</h1>
            <div class="intro-text">
                <div class="version">v8.0.0j b4 (s01.20)</div>
                <p>
                Добро пожаловать<br><br>               
                Данный проект - площадка для общения пользователей через текстовые посты 
                </p>
            </div>
            
            <div class="auth-buttons">
                <?php if(isset($_GET['q']) && $_GET['q'] == 1): ?>
                    <div id="qr_cont">
                        <?php if(isset($_SESSION['qr_image'])): ?>
                            <img id="qrImage" src="<?= htmlspecialchars($_SESSION['qr_image']) ?>" alt=" ">
                                
                            <script>                          
                              const token = "<?= $_SESSION['qt'] ?>";
                              
                              function check_status() {
                                  
                                  fetch("/php/qr-status.php?qt=" + encodeURIComponent(token))
                                          .then(r => r.text())
                                          .then(txt => {
                                              txt = txt.trim();
                                              
                                          if (txt === 'confirmed') {
                                              window.location.href = '/main';
                                          } else if (txt === 'expired') {
                                              document.getElementById('qrImage').remove();
                                              document.getElementById('statusCounter').textContent =
                                                 'ВРЕМЯ ЖИЗНИ ТОКЕНА ВЫШЛО';
                                          } else {
                                              setTimeout(check_status, 1000);
                                          }
                                      })
                                      .catch(() => {
                                        setTimeout(check_status, 1000);
                                      });
                                  }
                                  setTimeout(check_status, 1000);
                            </script>
                            
                            <div id="statusCounter" style="margin-top:10px; font-size:16px; color: #333;"></div>
                        <?php else: ?>
                            <p>QR-код не найден</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="GET" action="login">
                        <input type="hidden" name="q" value="1">
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-panel">
            <a class="logout" href="?logout=1"><button>Выйти</button></a>
            
            <div class="block">

                <div class="register">  
                    <div class="block-text">Регистрация</div>
                      <form method="POST" action="login?s=0"> 
                        <input type="text" placeholder="Логин" name="login"> 
                        <input type="password" placeholder="Пароль" name="pass"> 
                        <input type="password" placeholder="Повтор пароля" name="repeatpass"> 
                        <label>
                          <input type="checkbox" name="policy">  
                             Подтвердите создание учётной записи 
                        </label> 
                        <a class="status-button">
                            <button type="submit">Регистрация</button>
                        </a>
                      </form>
                    <b><?= htmlspecialchars($regstatus ?? '') ?></b>
                </div>

                <div class="login">
                  <div class="block-text">Авторизация</div>
   
                    <form method="POST" action="login?s=1<?= isset($_GET['qt']) ? '&qt='.urlencode($_GET['qt']) : '' ?>">
                        <input type="hidden" name="qt" value="<?= htmlspecialchars($_GET['qt'] ?? $_SESSION['qt'] ?? '') ?>">
                        <input type="text" placeholder="Логин" name="login">
                        <input type="password" placeholder="Пароль" name="pass">
                        <a class="status-button">
                            <button type="submit">Вход</button>
                        </a>
                    </form>
                                    
                    <div class="auth-buttons">
                        <?php if(isset($_GET['q']) && $_GET['q'] == 1): ?>
                            <div class="qr-close-btn">
                              <form method="GET" action="login">
                                <input type="hidden" name="q" value="0">
                                <button type="submit">Скрыть QR</button>
                              </form>
                            </div>
                            
                        <?php else: ?>
                            <form method="GET" action="login">
                                <input type="hidden" name="q" value="1">
                                <button type="submit">QR-вход</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <b><?= htmlspecialchars($logstatus ?? '') ?></b>
                </div>
            </div>
        </div>
    </div> 
</body>
</html>