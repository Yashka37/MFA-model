<?php
session_start();
header('Content-Type: text/plain');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if (empty($_GET['qt'])) {
        throw new Exception("QR-òîêåí íå ïîëó÷åí");
    }

    $conn = new mysqli("localhost", "root", "xxXX1234", "resourses");
    $token = $conn->real_escape_string($_GET['qt']);
    
    
    $token = $_GET['qt'] ?? '';
    $q = $conn->prepare("SELECT expire FROM qr_tokens WHERE token=? LIMIT 1");
    $q->bind_param("s", $token);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    
    if (!$row) {
        echo 'expired';
        exit;
    }
    
    if ($row['expire'] <= date('Y-m-d H:i:s')) {
        @unlink("/var/www/html/qr/{$token}.png");
        $conn->query("DELETE FROM qr_tokens WHERE token='".$conn->real_escape_string($token)."'");
        echo 'expired';
        exit;
    }
    
    
    $stmt = $conn->prepare("SELECT userid FROM qr_tokens
                            WHERE token = ? AND status = 'confirmed'
                            AND expire > NOW()");
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        
        $row = $result->fetch_assoc();
        
        $u_conn = new mysqli("localhost", "root", "xxXX1234", "UserInfo");
        $user = $u_conn->query("SELECT login, pass, role FROM users WHERE id = ".$row['userid'])->fetch_assoc();
        $u_conn->close();
        
        $_SESSION['userid'] = $row['userid'];
        $_SESSION['login'] = $user['login'];
        $_SESSION['role']  = $user['role'];
        
        
        $path = "/var/www/html/qr/{$token}.png";
        if (file_exists($path)) {
            unlink($path);
        }
        $conn->query("DELETE FROM qr_tokens WHERE token = '".$conn->real_escape_string($token)."'");
            
        echo 'confirmed';
    } else {
        echo 'pending';
    }
} catch (Exception $e) {
    echo 'error';
}
?>
