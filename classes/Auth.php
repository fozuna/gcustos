<?php
class Auth {
    public static function login(string $email, string $password): bool {
        $user = User::findByEmail($email);
        if (!$user) return false;
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            return true;
        }
        return false;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}
?>