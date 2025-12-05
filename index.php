<?php
session_start();

// Настройки базы данных для OpenServer
define('DB_HOST', 'MySQL-8.0');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lms_digital_altamimi');

// Создание соединения с базой данных
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Проверяем соединение
if ($conn->connect_error) {
    die("Ошибка подключения к MySQL: " . $conn->connect_error . 
        "<br><br>Убедитесь, что:<br>
        1. OpenServer запущен (зеленая иконка в трее)<br>
        2. MySQL модуль запущен в OpenServer<br>
        3. Проверьте настройки OpenServer -> Дополнительно -> MySQL<br>
        Имя сервера: localhost<br>
        Пользователь: root<br>
        Пароль: (оставить пустым)<br>
        Порт: 3306");
}

// Создаем базу данных если она не существует
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
} else {
    die("Ошибка создания базы данных: " . $conn->error);
}

// Функция создания таблиц
function createTables($conn) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            birth_year INT NOT NULL,
            user_group VARCHAR(50) NOT NULL,
            role ENUM('student', 'teacher', 'director') DEFAULT 'student',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS news (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            author_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            day_of_week ENUM('Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            subject_id INT,
            group_name VARCHAR(50) NOT NULL,
            teacher_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS journal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            date DATE NOT NULL,
            grade VARCHAR(2) DEFAULT 'НБ',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            UNIQUE KEY unique_journal_entry (student_id, subject_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            subject_id INT,
            teacher_id INT,
            student_id INT,
            task_type ENUM('Домашнее задание', 'СРС') NOT NULL,
            deadline DATETIME NOT NULL,
            status ENUM('активно', 'просрочено', 'выполнено') DEFAULT 'активно',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS student_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_subject (student_id, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS teacher_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            UNIQUE KEY unique_teacher_subject (teacher_id, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        if (!$conn->query($sql)) {
            die("Ошибка создания таблицы: " . $conn->error . "<br>SQL: " . $sql);
        }
    }
    
    // Добавляем тестовые данные если таблицы пустые
    addSampleData($conn);
}

// Функция добавления тестовых данных
function addSampleData($conn) {
    // Проверяем, есть ли уже пользователи
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Добавляем тестовых пользователей
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, first_name, last_name, birth_year, user_group, role) 
                      VALUES ('admin', '$hashed_password', 'Амир', 'Альтамими', 1985, 'Преподаватели', 'director')");
        
        $hashed_password = password_hash('teacher123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, first_name, last_name, birth_year, user_group, role) 
                      VALUES ('teacher', '$hashed_password', 'Алексей', 'Петров', 1980, 'Преподаватели', 'teacher')");
        
        $hashed_password = password_hash('student123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, first_name, last_name, birth_year, user_group, role) 
                      VALUES ('student', '$hashed_password', 'Иван', 'Иванов', 2003, 'Группа А-21', 'student')");
        
        $hashed_password = password_hash('student2', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, first_name, last_name, birth_year, user_group, role) 
                      VALUES ('student2', '$hashed_password', 'Мария', 'Сидорова', 2004, 'Группа А-21', 'student')");
        
        $hashed_password = password_hash('student3', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, first_name, last_name, birth_year, user_group, role) 
                      VALUES ('student3', '$hashed_password', 'Петр', 'Кузнецов', 2003, 'Группа Б-21', 'student')");
        
        // Получаем ID созданных пользователей
        $admin_id = 1;
        $teacher_id = 2;
        $student1_id = 3;
        $student2_id = 4;
        $student3_id = 5;
        
        // Добавляем тестовые предметы
        $conn->query("INSERT INTO subjects (name, description, created_by) VALUES 
                     ('Математика', 'Высшая математика и алгебра', $admin_id),
                     ('Программирование', 'Основы программирования на PHP и JavaScript', $admin_id),
                     ('Физика', 'Общая физика и механика', $admin_id),
                     ('История', 'История Казахстана', $admin_id),
                     ('География', 'Физическая и экономическая география', $admin_id)");
        
        // Добавляем новости
        $conn->query("INSERT INTO news (title, content, author_id) VALUES 
                     ('Открытие нового учебного года', 'Уважаемые студенты и преподаватели! Поздравляем с началом нового учебного года 2024!', $admin_id),
                     ('Техническое обслуживание платформы', '20 октября с 22:00 до 02:00 будет проводиться техническое обслуживание платформы.', $admin_id),
                     ('Конкурс на лучший проект', 'Объявляем конкурс на лучший IT-проект среди студентов. Призы: планшеты и сертификаты.', $admin_id)");
        
        // Добавляем расписание
        $conn->query("INSERT INTO schedule (day_of_week, start_time, end_time, subject_id, group_name, teacher_id) VALUES 
                     ('Понедельник', '09:00', '10:30', 1, 'Группа А-21', $teacher_id),
                     ('Понедельник', '10:45', '12:15', 2, 'Группа А-21', $teacher_id),
                     ('Вторник', '09:00', '10:30', 3, 'Группа А-21', $teacher_id),
                     ('Среда', '09:00', '10:30', 4, 'Группа А-21', $teacher_id),
                     ('Четверг', '09:00', '10:30', 5, 'Группа А-21', $teacher_id)");
        
        // Связываем студентов с предметами
        $conn->query("INSERT INTO student_subjects (student_id, subject_id) VALUES 
                     ($student1_id, 1), ($student1_id, 2), ($student1_id, 3), ($student1_id, 4), ($student1_id, 5),
                     ($student2_id, 1), ($student2_id, 2), ($student2_id, 3), ($student2_id, 4), ($student2_id, 5),
                     ($student3_id, 1), ($student3_id, 2), ($student3_id, 3)");
        
        // Связываем учителя с предметами
        $conn->query("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES 
                     ($teacher_id, 1), ($teacher_id, 2), ($teacher_id, 3), ($teacher_id, 4), ($teacher_id, 5)");
        
        // Добавляем тестовые оценки
        $dates = ['2024-01-10', '2024-01-17', '2024-01-24', '2024-01-31'];
        foreach ($dates as $date) {
            $conn->query("INSERT INTO journal (student_id, subject_id, date, grade) VALUES 
                         ($student1_id, 1, '$date', '5'),
                         ($student1_id, 2, '$date', '4'),
                         ($student1_id, 3, '$date', '5'),
                         ($student2_id, 1, '$date', '4'),
                         ($student2_id, 2, '$date', '3'),
                         ($student2_id, 3, '$date', 'НБ'),
                         ($student3_id, 1, '$date', '5'),
                         ($student3_id, 2, '$date', '5'),
                         ($student3_id, 3, '$date', '4')");
        }
        
        // Добавляем тестовые задания
        $conn->query("INSERT INTO assignments (title, description, subject_id, teacher_id, student_id, task_type, deadline) VALUES 
                     ('Домашнее задание по математике', 'Решить задачи 1-10 из учебника', 1, $teacher_id, $student1_id, 'Домашнее задание', '2024-02-15 23:59:00'),
                     ('СРС по программированию', 'Написать программу калькулятор', 2, $teacher_id, $student1_id, 'СРС', '2024-02-20 23:59:00'),
                     ('Домашнее задание по физике', 'Подготовить доклад', 3, $teacher_id, $student2_id, 'Домашнее задание', '2024-02-18 23:59:00'),
                     ('СРС по географии', 'Создать карту регионов', 5, $teacher_id, $student3_id, 'СРС', '2024-02-25 23:59:00')");
    }
}

// Создаем таблицы если они не существуют
createTables($conn);

// Обработка регистрации с выбором роли
if (isset($_POST['register'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $birth_year = (int)$_POST['birth_year'];
    $group = $conn->real_escape_string($_POST['group']);
    $role = $conn->real_escape_string($_POST['role']);
    
    $sql = "INSERT INTO users (username, password, first_name, last_name, birth_year, user_group, role) 
            VALUES ('$username', '$password', '$first_name', '$last_name', '$birth_year', '$group', '$role')";
    
    if ($conn->query($sql)) {
        // Если это студент, добавляем ему все предметы
        if ($role == 'student') {
            $new_user_id = $conn->insert_id;
            $subjects = $conn->query("SELECT id FROM subjects");
            while ($subject = $subjects->fetch_assoc()) {
                $conn->query("INSERT INTO student_subjects (student_id, subject_id) VALUES ($new_user_id, {$subject['id']})");
            }
        }
        
        $_SESSION['message'] = "Регистрация успешна! Теперь войдите в систему.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=login");
        exit();
    } else {
        $error = "Ошибка регистрации: " . $conn->error;
    }
}

// Обработка входа
if (isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['group'] = $user['user_group'];
            $_SESSION['birth_year'] = $user['birth_year'];
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Неверный пароль!";
        }
    } else {
        $error = "Пользователь не найден!";
    }
}

// Выход из системы
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Определение текущей страницы
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
if (!isset($_SESSION['user_id']) && $page != 'login' && $page != 'register') {
    $page = 'login';
}

// Функции для работы с данными
function getUserSubjects($conn, $user_id) {
    $sql = "SELECT s.* FROM subjects s 
            JOIN student_subjects ss ON s.id = ss.subject_id 
            WHERE ss.student_id = $user_id 
            ORDER BY s.name";
    return $conn->query($sql);
}

function getStudentGrades($conn, $student_id, $subject_id = null) {
    if ($subject_id) {
        $sql = "SELECT * FROM journal WHERE student_id = $student_id AND subject_id = $subject_id ORDER BY date DESC";
    } else {
        $sql = "SELECT j.*, s.name as subject_name FROM journal j 
                JOIN subjects s ON j.subject_id = s.id 
                WHERE j.student_id = $student_id ORDER BY j.date DESC";
    }
    return $conn->query($sql);
}

function getStudentRanking($conn) {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.user_group, 
                   AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) as avg_grade,
                   COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) as grade_count,
                   COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) * AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) as ranking_score
            FROM users u
            LEFT JOIN journal j ON u.id = j.student_id
            WHERE u.role = 'student'
            GROUP BY u.id
            HAVING grade_count > 0
            ORDER BY ranking_score DESC, avg_grade DESC, grade_count DESC
            LIMIT 5";
    return $conn->query($sql);
}

function getCurrentStudentPosition($conn, $student_id) {
    $sql = "SELECT position FROM (
                SELECT u.id, 
                       ROW_NUMBER() OVER (ORDER BY 
                           COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) * 
                           AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) DESC,
                           AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) DESC,
                           COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) DESC
                       ) as position
                FROM users u
                LEFT JOIN journal j ON u.id = j.student_id
                WHERE u.role = 'student'
                GROUP BY u.id
                HAVING COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) > 0
            ) ranked WHERE id = $student_id";
    $result = $conn->query($sql);
    return $result->num_rows > 0 ? $result->fetch_assoc()['position'] : null;
}

function getTeacherSubjects($conn, $teacher_id) {
    $sql = "SELECT s.* FROM subjects s 
            JOIN teacher_subjects ts ON s.id = ts.subject_id 
            WHERE ts.teacher_id = $teacher_id 
            ORDER BY s.name";
    return $conn->query($sql);
}

function getStudentsForSubject($conn, $subject_id) {
    $sql = "SELECT u.* FROM users u 
            JOIN student_subjects ss ON u.id = ss.student_id 
            WHERE ss.subject_id = $subject_id AND u.role = 'student' 
            ORDER BY u.last_name, u.first_name";
    return $conn->query($sql);
}

function getStudentAssignmentsCount($conn, $student_id) {
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'активно' AND deadline > NOW() THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'просрочено' OR (status = 'активно' AND deadline < NOW()) THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN status = 'выполнено' THEN 1 ELSE 0 END) as completed
            FROM assignments WHERE student_id = $student_id";
    return $conn->query($sql)->fetch_assoc();
}

function getStudentAverageGrade($conn, $student_id) {
    $sql = "SELECT AVG(CASE WHEN grade != 'НБ' THEN CAST(grade AS UNSIGNED) ELSE NULL END) as avg_grade 
            FROM journal WHERE student_id = $student_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['avg_grade'] ? round($row['avg_grade'], 1) : 0;
}

// Обработка действий учителя/директора
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['teacher', 'director'])) {
    // Добавление предмета
    if (isset($_POST['add_subject'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        $conn->query("INSERT INTO subjects (name, description, created_by) VALUES ('$name', '$description', {$_SESSION['user_id']})");
        
        // Если директор добавляет предмет, привязываем его ко всем учителям
        if ($_SESSION['role'] == 'director') {
            $new_subject_id = $conn->insert_id;
            $teachers = $conn->query("SELECT id FROM users WHERE role = 'teacher'");
            while ($teacher = $teachers->fetch_assoc()) {
                $conn->query("INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES ({$teacher['id']}, $new_subject_id)");
            }
        }
    }
    
    // Добавление расписания
    if (isset($_POST['add_schedule'])) {
        $day = $conn->real_escape_string($_POST['day']);
        $start_time = $conn->real_escape_string($_POST['start_time']);
        $end_time = $conn->real_escape_string($_POST['end_time']);
        $subject_id = (int)$_POST['subject_id'];
        $group = $conn->real_escape_string($_POST['group']);
        
        $conn->query("INSERT INTO schedule (day_of_week, start_time, end_time, subject_id, group_name, teacher_id) 
                     VALUES ('$day', '$start_time', '$end_time', $subject_id, '$group', {$_SESSION['user_id']})");
    }
    
    // Добавление оценки в журнал (через AJAX)
    if (isset($_POST['ajax_add_grade'])) {
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $date = $conn->real_escape_string($_POST['date']);
        $grade = $conn->real_escape_string($_POST['grade']);
        
        $sql = "INSERT INTO journal (student_id, subject_id, date, grade) 
                VALUES ($student_id, $subject_id, '$date', '$grade')
                ON DUPLICATE KEY UPDATE grade = '$grade'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Оценка сохранена']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $conn->error]);
        }
        exit();
    }
    
    // Загрузка журнала для учителя (AJAX)
    if (isset($_POST['load_journal'])) {
        $subject_id = (int)$_POST['subject_id'];
        $date = $conn->real_escape_string($_POST['date']);
        
        $students = getStudentsForSubject($conn, $subject_id);
        
        echo '<div class="table-responsive">';
        echo '<table class="table table-hover table-custom">';
        echo '<thead><tr><th>Студент</th><th>Группа</th><th>Оценка за ' . $date . '</th><th>Действие</th></tr></thead>';
        echo '<tbody>';
        
        while ($student = $students->fetch_assoc()) {
            // Получаем текущую оценку за выбранную дату
            $grade_sql = "SELECT grade FROM journal WHERE student_id = {$student['id']} AND subject_id = $subject_id AND date = '$date'";
            $grade_result = $conn->query($grade_sql);
            $current_grade = $grade_result->num_rows > 0 ? $grade_result->fetch_assoc()['grade'] : 'НБ';
            
            echo '<tr>';
            echo '<td>' . $student['last_name'] . ' ' . $student['first_name'] . '</td>';
            echo '<td>' . $student['user_group'] . '</td>';
            echo '<td>';
            echo '<select class="form-control form-control-sm grade-select" id="grade_' . $student['id'] . '" style="width: 80px;">';
            echo '<option value="НБ"' . ($current_grade == 'НБ' ? ' selected' : '') . '>НБ</option>';
            for ($i = 2; $i <= 5; $i++) {
                echo '<option value="' . $i . '"' . ($current_grade == $i ? ' selected' : '') . '>' . $i . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '<td>';
            echo '<button class="btn btn-sm btn-primary save-grade-btn" 
                    onclick="saveGrade(' . $student['id'] . ', ' . $subject_id . ')">
                    Сохранить
                  </button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table></div>';
        exit();
    }
    
    // Добавление задания
    if (isset($_POST['add_assignment'])) {
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);
        $subject_id = (int)$_POST['subject_id'];
        $student_id = (int)$_POST['student_id'];
        $task_type = $conn->real_escape_string($_POST['task_type']);
        $deadline = $conn->real_escape_string($_POST['deadline']);
        
        $conn->query("INSERT INTO assignments (title, description, subject_id, teacher_id, student_id, task_type, deadline) 
                     VALUES ('$title', '$description', $subject_id, {$_SESSION['user_id']}, $student_id, '$task_type', '$deadline')");
    }
    
    // Удаление расписания
    if (isset($_GET['delete_schedule'])) {
        $schedule_id = (int)$_GET['delete_schedule'];
        $conn->query("DELETE FROM schedule WHERE id = $schedule_id");
        header("Location: ?page=schedule");
        exit();
    }
    
    // Редактирование расписания
    if (isset($_POST['edit_schedule'])) {
        $schedule_id = (int)$_POST['schedule_id'];
        $day = $conn->real_escape_string($_POST['day']);
        $start_time = $conn->real_escape_string($_POST['start_time']);
        $end_time = $conn->real_escape_string($_POST['end_time']);
        $subject_id = (int)$_POST['subject_id'];
        $group = $conn->real_escape_string($_POST['group']);
        
        $conn->query("UPDATE schedule SET 
                     day_of_week = '$day', 
                     start_time = '$start_time', 
                     end_time = '$end_time', 
                     subject_id = $subject_id, 
                     group_name = '$group' 
                     WHERE id = $schedule_id");
    }
}

// Получение данных для страниц
$news = $conn->query("SELECT n.*, u.first_name, u.last_name FROM news n LEFT JOIN users u ON n.author_id = u.id ORDER BY created_at DESC");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
$students = $conn->query("SELECT * FROM users WHERE role = 'student' ORDER BY last_name, first_name");

// Получение статистики для текущего пользователя
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'student') {
        $assignments_stats = getStudentAssignmentsCount($conn, $_SESSION['user_id']);
        $avg_grade = getStudentAverageGrade($conn, $_SESSION['user_id']);
        $current_position = getCurrentStudentPosition($conn, $_SESSION['user_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digi-mimi - LMS система</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0A2463;
            --primary-blue: #1E40AF;
            --accent-blue: #3B82F6;
            --light-blue: #60A5FA;
            --white: #FFFFFF;
            --gray-light: #F8FAFC;
            --gray: #64748B;
            --gold: #FFD700;
            --silver: #C0C0C0;
            --bronze: #CD7F32;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        /* Навигационная панель */
        .navbar-custom {
            background: var(--primary-dark) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            color: var(--white) !important;
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-brand i {
            color: var(--light-blue);
        }
        
        .nav-link {
            color: var(--white) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 0 2px;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .nav-link .badge {
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        /* Основной контейнер */
        .main-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            padding: 2rem;
            min-height: calc(100vh - 100px);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Карточки */
        .card-custom {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: white;
            border: none;
            padding: 1.2rem 1.5rem;
            font-weight: 600;
        }
        
        /* Кнопки */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        /* Формы */
        .form-control-custom {
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control-custom:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Таблицы */
        .table-custom {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .table-custom thead {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: white;
        }
        
        /* Рейтинг - Стиль Duolingo */
        .duolingo-container {
            background: linear-gradient(135deg, #58CC02 0%, #1CB0F6 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .duolingo-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            animation: floatBackground 20s linear infinite;
        }
        
        @keyframes floatBackground {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(20px, 20px) rotate(360deg); }
        }
        
        .ranking-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .ranking-podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            height: 300px;
            margin: 3rem 0;
            position: relative;
        }
        
        .podium-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 10px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .podium-item:hover {
            transform: translateY(-10px);
        }
        
        .podium-place {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .podium-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin-bottom: 15px;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .podium-bar {
            width: 100%;
            border-radius: 10px 10px 0 0;
            position: relative;
            transition: all 0.5s ease;
        }
        
        .podium-1 .podium-bar {
            height: 200px;
            background: linear-gradient(135deg, var(--gold), #FFEC8B);
            animation: glowGold 2s infinite alternate;
        }
        
        .podium-2 .podium-bar {
            height: 160px;
            background: linear-gradient(135deg, var(--silver), #E8E8E8);
            animation: glowSilver 2s infinite alternate;
        }
        
        .podium-3 .podium-bar {
            height: 120px;
            background: linear-gradient(135deg, var(--bronze), #E8B886);
            animation: glowBronze 2s infinite alternate;
        }
        
        .podium-4 .podium-bar {
            height: 90px;
            background: linear-gradient(135deg, #4CAF50, #81C784);
        }
        
        .podium-5 .podium-bar {
            height: 60px;
            background: linear-gradient(135deg, #2196F3, #64B5F6);
        }
        
        @keyframes glowGold {
            from { box-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }
            to { box-shadow: 0 0 40px rgba(255, 215, 0, 0.8); }
        }
        
        @keyframes glowSilver {
            from { box-shadow: 0 0 15px rgba(192, 192, 192, 0.5); }
            to { box-shadow: 0 0 30px rgba(192, 192, 192, 0.8); }
        }
        
        @keyframes glowBronze {
            from { box-shadow: 0 0 10px rgba(205, 127, 50, 0.5); }
            to { box-shadow: 0 0 25px rgba(205, 127, 50, 0.8); }
        }
        
        .podium-info {
            position: absolute;
            bottom: -60px;
            text-align: center;
            width: 100%;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .podium-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .podium-score {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .ranking-stats {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 3rem;
            backdrop-filter: blur(10px);
        }
        
        .xp-bar {
            height: 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .xp-fill {
            height: 100%;
            background: linear-gradient(90deg, #FFD700, #FFEC8B);
            border-radius: 10px;
            transition: width 1s ease;
        }
        
        /* Анимации */
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .bounce {
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        /* Журнал учителя */
        .journal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .journal-student-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .journal-student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        /* Аутентификация */
        .auth-container {
            max-width: 500px;
            margin: 5rem auto;
            animation: fadeIn 0.5s ease;
        }
        
        .auth-card {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.15);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-large {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }
        
        /* Уведомления */
        .alert-custom {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        /* Предметные кнопки */
        .subject-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .subject-btn {
            padding: 20px 15px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        
        .subject-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .subject-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .auth-container {
                margin: 2rem 1rem;
            }
            
            .auth-card {
                padding: 2rem 1.5rem;
            }
            
            .podium-item {
                margin: 0 5px;
            }
            
            .podium-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .podium-place {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Навигационная панель -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="?page=home">
                <i class="fas fa-graduation-cap"></i>
                Digi-mimi
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $page == 'home' ? 'active' : '' ?>" href="?page=home">
                            <i class="fas fa-home"></i> Главная
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page == 'schedule' ? 'active' : '' ?>" href="?page=schedule">
                            <i class="fas fa-calendar-alt"></i> Расписание
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page == 'activity' ? 'active' : '' ?>" href="?page=activity">
                            <i class="fas fa-chart-line"></i> Активность
                            <?php if ($_SESSION['role'] == 'student' && isset($assignments_stats['active']) && $assignments_stats['active'] > 0): ?>
                            <span class="badge bg-danger"><?= $assignments_stats['active'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (in_array($_SESSION['role'], ['teacher', 'director'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $page == 'journal' ? 'active' : '' ?>" href="?page=journal">
                            <i class="fas fa-book"></i> Журнал
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $page == 'rating' ? 'active' : '' ?>" href="?page=rating">
                            <i class="fas fa-trophy"></i> Рейтинг
                            <?php if ($_SESSION['role'] == 'student' && isset($current_position) && $current_position <= 5): ?>
                            <span class="badge bg-warning float">Топ-<?= $current_position ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page == 'assignments' ? 'active' : '' ?>" href="?page=assignments">
                            <i class="fas fa-tasks"></i> Задания
                            <?php if ($_SESSION['role'] == 'student' && isset($assignments_stats['active']) && $assignments_stats['active'] > 0): ?>
                            <span class="badge bg-danger"><?= $assignments_stats['active'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?= $_SESSION['first_name'] ?>
                            <?php if ($_SESSION['role'] == 'student' && isset($assignments_stats['active']) && $assignments_stats['active'] > 0): ?>
                            <span class="badge bg-danger" style="position: absolute; top: 0; right: 0; transform: translate(50%, -50%);"><?= $assignments_stats['active'] ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?page=profile"><i class="fas fa-user"></i> Личный кабинет</a></li>
                            <?php if ($_SESSION['role'] == 'director'): ?>
                            <li><a class="dropdown-item" href="?page=admin"><i class="fas fa-cog"></i> Админ панель</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?logout=1"><i class="fas fa-sign-out-alt"></i> Выйти</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Основной контент -->
    <div class="container main-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php
        // Отображение текущей страницы
        switch ($page) {
            case 'login':
                includeLoginPage();
                break;
            case 'register':
                includeRegisterPage();
                break;
            case 'home':
                includeHomePage();
                break;
            case 'schedule':
                includeSchedulePage();
                break;
            case 'activity':
                includeActivityPage();
                break;
            case 'journal':
                includeJournalPage();
                break;
            case 'rating':
                includeRatingPage();
                break;
            case 'assignments':
                includeAssignmentsPage();
                break;
            case 'profile':
                includeProfilePage();
                break;
            case 'admin':
                includeAdminPage();
                break;
            default:
                includeHomePage();
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
    // Анимация для рейтинга
    $(document).ready(function() {
        // Анимация элементов рейтинга
        $('.podium-item').each(function(i) {
            $(this).css('opacity', 0).delay(i * 300).animate({opacity: 1}, 500);
        });
        
        // Анимация заполнения прогресс-баров
        $('.xp-fill').each(function() {
            var width = $(this).data('width') || $(this).css('width');
            $(this).css('width', '0').animate({width: width}, 1500);
        });
        
        // Периодическая проверка новых заданий
        setInterval(checkNewAssignments, 30000);
    });
    
    function checkNewAssignments() {
        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: {check_assignments: 1},
            success: function(response) {
                // Здесь можно обновить счетчики заданий
            }
        });
    }
    
    // Функция для сохранения оценок в журнале
    function saveGrade(studentId, subjectId) {
        let date = document.getElementById('journal_date').value;
        let grade = document.getElementById('grade_' + studentId).value;
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                ajax_add_grade: 1,
                student_id: studentId,
                subject_id: subjectId,
                date: date,
                grade: grade
            },
            success: function(response) {
                let result = JSON.parse(response);
                if (result.success) {
                    showNotification(result.message, 'success');
                    
                    // Плавно обновляем кнопку
                    let btn = $('.save-grade-btn[onclick*="' + studentId + '"]');
                    btn.removeClass('btn-primary').addClass('btn-success').html('<i class="fas fa-check"></i> Сохранено');
                    setTimeout(() => {
                        btn.removeClass('btn-success').addClass('btn-primary').html('Сохранить');
                    }, 2000);
                } else {
                    showNotification(result.message, 'danger');
                }
            },
            error: function() {
                showNotification('Ошибка соединения с сервером', 'danger');
            }
        });
    }
    
    // Функция для загрузки журнала
    function loadJournal() {
        let subjectId = document.getElementById('journal_subject').value;
        let date = document.getElementById('journal_date').value;
        
        if (!subjectId) {
            $('#journalContent').html('<div class="text-center p-5"><div class="icon-large"><i class="fas fa-book-open"></i></div><h5>Выберите предмет для просмотра журнала</h5><p class="text-muted">Выберите предмет из списка выше, чтобы начать работу с журналом</p></div>');
            return;
        }
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                load_journal: 1,
                subject_id: subjectId,
                date: date
            },
            success: function(response) {
                $('#journalContent').html(response);
            }
        });
    }
    
    // Функция для показа уведомлений
    function showNotification(message, type = 'success') {
        let alert = $('<div class="alert alert-' + type + ' alert-custom alert-dismissible fade show">' +
                     message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        $('.main-container').prepend(alert);
        setTimeout(() => alert.alert('close'), 5000);
    }
    
    // Функция для удаления расписания
    function deleteSchedule(id) {
        if (confirm('Вы уверены, что хотите удалить это занятие?')) {
            window.location.href = '?page=schedule&delete_schedule=' + id;
        }
    }
    
    // Функция для редактирования расписания
    function editSchedule(id) {
        // Здесь можно реализовать модальное окно для редактирования
        // Пока просто показываем сообщение
        showNotification('Функция редактирования расписания в разработке', 'info');
    }
    </script>
</body>
</html>

<?php
// Функции для отображения страниц
function includeLoginPage() {
    ?>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-large">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>Digi-mimi</h2>
                <p class="text-muted">Войдите в свою учетную запись</p>
            </div>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Имя пользователя</label>
                    <input type="text" class="form-control form-control-custom" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Пароль</label>
                    <input type="password" class="form-control form-control-custom" id="password" name="password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="login" class="btn btn-primary-custom">Войти</button>
                </div>
                
                <div class="text-center mt-3">
                    <p>Нет аккаунта? <a href="?page=register" class="text-decoration-none">Зарегистрироваться</a></p>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function includeRegisterPage() {
    ?>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-large">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Регистрация</h2>
                <p class="text-muted">Создайте новую учетную запись</p>
            </div>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">Имя</label>
                        <input type="text" class="form-control form-control-custom" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Фамилия</label>
                        <input type="text" class="form-control form-control-custom" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="birth_year" class="form-label">Год рождения</label>
                    <input type="number" class="form-control form-control-custom" id="birth_year" name="birth_year" min="1900" max="2010" required>
                </div>
                
                <div class="mb-3">
                    <label for="group" class="form-label">Группа</label>
                    <input type="text" class="form-control form-control-custom" id="group" name="group" required>
                </div>
                
                <div class="mb-3">
                    <label for="role" class="form-label">Роль</label>
                    <select class="form-control form-control-custom" id="role" name="role" required>
                        <option value="student">Студент</option>
                        <option value="teacher">Преподаватель</option>
                        <option value="director">Директор</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Имя пользователя</label>
                    <input type="text" class="form-control form-control-custom" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Пароль</label>
                    <input type="password" class="form-control form-control-custom" id="password" name="password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="register" class="btn btn-primary-custom">Зарегистрироваться</button>
                </div>
                
                <div class="text-center mt-3">
                    <p>Уже есть аккаунт? <a href="?page=login" class="text-decoration-none">Войти</a></p>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function includeHomePage() {
    global $news;
    ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-newspaper"></i> Последние новости</h4>
                </div>
                <div class="card-body">
                    <?php while ($row = $news->fetch_assoc()): ?>
                    <div class="news-item mb-4 pb-3 border-bottom">
                        <h5><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="text-muted mb-2">
                            <i class="far fa-calendar"></i> <?= date('d.m.Y', strtotime($row['created_at'])) ?>
                            <?php if ($row['first_name']): ?>
                            | <i class="fas fa-user"></i> <?= $row['first_name'] . ' ' . $row['last_name'] ?>
                            <?php endif; ?>
                        </p>
                        <p><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-user-circle"></i> Мой профиль</h4>
                </div>
                <div class="card-body text-center">
                    <div class="icon-large">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h4><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></h4>
                    <p class="text-muted"><?= $_SESSION['role'] == 'student' ? 'Студент' : ($_SESSION['role'] == 'teacher' ? 'Преподаватель' : 'Директор') ?></p>
                    <div class="row">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <h5 class="mb-0"><?= $_SESSION['group'] ?></h5>
                                <small>Группа</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <h5 class="mb-0"><?= date('Y') - $_SESSION['birth_year'] ?></h5>
                                <small>Возраст</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card card-custom mt-3">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-bolt"></i> Быстрый доступ</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?page=schedule" class="btn btn-outline-primary">Расписание</a>
                        <a href="?page=assignments" class="btn btn-outline-success">Мои задания</a>
                        <a href="?page=activity" class="btn btn-outline-info">Активность</a>
                        <a href="?page=rating" class="btn btn-outline-warning">Рейтинг</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function includeSchedulePage() {
    global $conn;
    
    // Получаем расписание в зависимости от роли
    if ($_SESSION['role'] == 'student') {
        $schedule = $conn->query("SELECT s.*, sub.name as subject_name, u.first_name, u.last_name 
                                 FROM schedule s 
                                 LEFT JOIN subjects sub ON s.subject_id = sub.id 
                                 LEFT JOIN users u ON s.teacher_id = u.id 
                                 WHERE s.group_name = '{$_SESSION['group']}'
                                 ORDER BY 
                                     FIELD(day_of_week, 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'),
                                     start_time");
    } else {
        $schedule = $conn->query("SELECT s.*, sub.name as subject_name, u.first_name, u.last_name 
                                 FROM schedule s 
                                 LEFT JOIN subjects sub ON s.subject_id = sub.id 
                                 LEFT JOIN users u ON s.teacher_id = u.id 
                                 ORDER BY 
                                     FIELD(day_of_week, 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'),
                                     start_time");
    }
    ?>
    <div class="row">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-calendar-alt"></i> Расписание занятий</h4>
                    <?php if (in_array($_SESSION['role'], ['teacher', 'director'])): ?>
                    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <i class="fas fa-plus"></i> Добавить занятие
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom">
                            <thead>
                                <tr>
                                    <th>День недели</th>
                                    <th>Время</th>
                                    <th>Предмет</th>
                                    <th>Группа</th>
                                    <th>Преподаватель</th>
                                    <?php if (in_array($_SESSION['role'], ['teacher', 'director'])): ?>
                                    <th>Действия</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $schedule->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= $row['day_of_week'] ?></strong></td>
                                    <td><?= date('H:i', strtotime($row['start_time'])) ?> - <?= date('H:i', strtotime($row['end_time'])) ?></td>
                                    <td><?= $row['subject_name'] ?></td>
                                    <td><span class="badge bg-primary"><?= $row['group_name'] ?></span></td>
                                    <td><?= $row['first_name'] . ' ' . $row['last_name'] ?></td>
                                    <?php if (in_array($_SESSION['role'], ['teacher', 'director'])): ?>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editSchedule(<?= $row['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?= $row['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (in_array($_SESSION['role'], ['teacher', 'director'])): ?>
    <!-- Модальное окно добавления занятия -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue)); color: white;">
                    <h5 class="modal-title">Добавить занятие</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="day" class="form-label">День недели</label>
                            <select class="form-control form-control-custom" id="day" name="day" required>
                                <option value="Понедельник">Понедельник</option>
                                <option value="Вторник">Вторник</option>
                                <option value="Среда">Среда</option>
                                <option value="Четверг">Четверг</option>
                                <option value="Пятница">Пятница</option>
                                <option value="Суббота">Суббота</option>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">Начало</label>
                                <input type="time" class="form-control form-control-custom" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">Окончание</label>
                                <input type="time" class="form-control form-control-custom" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Предмет</label>
                            <select class="form-control form-control-custom" id="subject_id" name="subject_id" required>
                                <?php
                                $subjects = $conn->query("SELECT * FROM subjects");
                                while ($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="group" class="form-label">Группа</label>
                            <input type="text" class="form-control form-control-custom" id="group" name="group" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="add_schedule" class="btn btn-primary-custom">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
}

function includeActivityPage() {
    global $conn;
    
    if ($_SESSION['role'] == 'student') {
        $student_id = $_SESSION['user_id'];
        $subjects = getUserSubjects($conn, $student_id);
        
        if (isset($_GET['subject_id'])) {
            $subject_id = (int)$_GET['subject_id'];
            $grades = getStudentGrades($conn, $student_id, $subject_id);
            
            // Получаем информацию о предмете
            $subject_info = $conn->query("SELECT * FROM subjects WHERE id = $subject_id")->fetch_assoc();
            ?>
            <div class="row">
                <div class="col-12">
                    <div class="card card-custom">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-chart-line"></i> Активность по предмету: <?= $subject_info['name'] ?></h4>
                            <a href="?page=activity" class="btn btn-primary-custom">
                                <i class="fas fa-arrow-left"></i> Назад к выбору
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <h5>Описание предмета:</h5>
                                    <p class="text-muted"><?= $subject_info['description'] ?></p>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6>Статистика по предмету</h6>
                                            <?php
                                            $stats = $conn->query("SELECT 
                                                COUNT(*) as total,
                                                SUM(CASE WHEN grade != 'НБ' THEN 1 ELSE 0 END) as attended,
                                                AVG(CASE WHEN grade != 'НБ' THEN CAST(grade AS UNSIGNED) ELSE NULL END) as avg_grade
                                                FROM journal WHERE student_id = $student_id AND subject_id = $subject_id")->fetch_assoc();
                                            
                                            $attendance_rate = $stats['total'] > 0 ? round(($stats['attended'] / $stats['total']) * 100) : 0;
                                            $avg_grade = $stats['avg_grade'] ? round($stats['avg_grade'], 1) : 0;
                                            ?>
                                            <div class="mt-3">
                                                <div class="mb-2">
                                                    <span class="badge bg-primary">Средний балл: <?= $avg_grade ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge bg-info">Посещаемость: <?= $attendance_rate ?>%</span>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge bg-secondary">Всего занятий: <?= $stats['total'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover table-custom">
                                    <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Оценка</th>
                                            <th>Статус</th>
                                            <th>Прогресс</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $has_data = false;
                                        while ($row = $grades->fetch_assoc()): 
                                            $has_data = true;
                                        ?>
                                        <tr>
                                            <td><?= date('d.m.Y', strtotime($row['date'])) ?></td>
                                            <td>
                                                <?php if ($row['grade'] == 'НБ'): ?>
                                                <span class="badge bg-secondary">НБ</span>
                                                <?php else: ?>
                                                <span class="badge bg-<?= $row['grade'] >= 4 ? 'success' : ($row['grade'] >= 3 ? 'warning' : 'danger') ?>">
                                                    <?= $row['grade'] ?>
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['grade'] == 'НБ'): ?>
                                                <span class="text-muted"><i class="fas fa-times-circle"></i> Не было</span>
                                                <?php else: ?>
                                                <span class="text-success"><i class="fas fa-check-circle"></i> Оценка выставлена</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 10px;">
                                                    <?php if ($row['grade'] != 'НБ'): ?>
                                                    <div class="progress-bar bg-<?= $row['grade'] >= 4 ? 'success' : ($row['grade'] >= 3 ? 'warning' : 'danger') ?>" 
                                                         style="width: <?= ($row['grade'] / 5) * 100 ?>%"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        
                                        <?php if (!$has_data): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">
                                                <div class="p-5">
                                                    <div class="icon-large">
                                                        <i class="fas fa-chart-bar"></i>
                                                    </div>
                                                    <h5>Нет данных об активности</h5>
                                                    <p class="text-muted">По этому предмету еще нет оценок</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="row">
                <div class="col-12">
                    <div class="card card-custom">
                        <div class="card-header card-header-custom">
                            <h4 class="mb-0"><i class="fas fa-book"></i> Выберите предмет для просмотра активности</h4>
                        </div>
                        <div class="card-body">
                            <div class="subject-buttons">
                                <?php 
                                $has_subjects = false;
                                while ($subject = $subjects->fetch_assoc()): 
                                    $has_subjects = true;
                                    
                                    // Получаем статистику по предмету
                                    $stats = $conn->query("SELECT 
                                        COUNT(*) as total,
                                        SUM(CASE WHEN grade != 'НБ' THEN 1 ELSE 0 END) as attended,
                                        AVG(CASE WHEN grade != 'НБ' THEN CAST(grade AS UNSIGNED) ELSE NULL END) as avg_grade
                                        FROM journal WHERE student_id = {$_SESSION['user_id']} AND subject_id = {$subject['id']}")->fetch_assoc();
                                    
                                    $attendance_rate = $stats['total'] > 0 ? round(($stats['attended'] / $stats['total']) * 100) : 0;
                                    $avg_grade = $stats['avg_grade'] ? round($stats['avg_grade'], 1) : 0;
                                    $icon_class = '';
                                    
                                    // Выбираем иконку в зависимости от предмета
                                    $subject_name = strtolower($subject['name']);
                                    if (strpos($subject_name, 'матем') !== false) $icon_class = 'fas fa-calculator';
                                    elseif (strpos($subject_name, 'программ') !== false) $icon_class = 'fas fa-code';
                                    elseif (strpos($subject_name, 'физик') !== false) $icon_class = 'fas fa-atom';
                                    elseif (strpos($subject_name, 'истори') !== false) $icon_class = 'fas fa-landmark';
                                    elseif (strpos($subject_name, 'географи') !== false) $icon_class = 'fas fa-globe-europe';
                                    else $icon_class = 'fas fa-book';
                                ?>
                                <button class="subject-btn" onclick="window.location.href='?page=activity&subject_id=<?= $subject['id'] ?>'">
                                    <div class="subject-icon">
                                        <i class="<?= $icon_class ?>"></i>
                                    </div>
                                    <div><?= $subject['name'] ?></div>
                                    <?php if ($avg_grade > 0): ?>
                                    <small style="margin-top: 5px; opacity: 0.9;">Средний балл: <?= $avg_grade ?></small>
                                    <?php endif; ?>
                                </button>
                                <?php endwhile; ?>
                                
                                <?php if (!$has_subjects): ?>
                                <div class="col-12 text-center p-5">
                                    <div class="icon-large">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <h5>Нет доступных предметов</h5>
                                    <p class="text-muted">Обратитесь к администратору для добавления предметов</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        // Для учителей и директоров показываем информацию об их предметах
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-chart-line"></i> Общая активность системы</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Преподаватели и директоры могут просматривать активность через журнал оценок.</p>
                        <a href="?page=journal" class="btn btn-primary-custom">
                            <i class="fas fa-book"></i> Перейти в журнал оценок
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

function includeJournalPage() {
    global $conn;
    
    if (in_array($_SESSION['role'], ['teacher', 'director'])) {
        $teacher_id = $_SESSION['user_id'];
        
        // Получаем предметы учителя
        if ($_SESSION['role'] == 'teacher') {
            $teacher_subjects = getTeacherSubjects($conn, $teacher_id);
        } else {
            // Директор видит все предметы
            $teacher_subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
        }
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-book"></i> Электронный журнал оценок</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="journal_subject" class="form-label">Выберите предмет</label>
                                <select class="form-control form-control-custom" id="journal_subject" onchange="loadJournal()">
                                    <option value="">Выберите предмет...</option>
                                    <?php
                                    while ($subject = $teacher_subjects->fetch_assoc()): ?>
                                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="journal_date" class="form-label">Дата занятия</label>
                                <input type="date" class="form-control form-control-custom" id="journal_date" 
                                       value="<?= date('Y-m-d') ?>" onchange="loadJournal()">
                            </div>
                        </div>
                        
                        <div id="journalContent">
                            <div class="text-center p-5">
                                <div class="icon-large">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <h5>Выберите предмет для просмотра журнала</h5>
                                <p class="text-muted">Выберите предмет и дату из списка выше, чтобы начать работу с журналом</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-book"></i> Доступ запрещен</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="icon-large text-danger">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h5 class="mt-3">Журнал оценок доступен только преподавателям и директорам</h5>
                        <p class="text-muted">Для просмотра своих оценок используйте страницу "Активность"</p>
                        <a href="?page=activity" class="btn btn-primary-custom mt-3">
                            <i class="fas fa-chart-line"></i> Перейти к активности
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

function includeRatingPage() {
    global $conn;
    
    // Получаем рейтинг студентов
    $ranking = getStudentRanking($conn);
    $ranking_data = [];
    $rank = 1;
    
    while ($row = $ranking->fetch_assoc()) {
        $row['rank'] = $rank;
        $ranking_data[] = $row;
        $rank++;
    }
    
    // Получаем статистику для текущего студента (если он вошел как студент)
    $current_student_stats = null;
    if ($_SESSION['role'] == 'student') {
        $student_id = $_SESSION['user_id'];
        $current_student_stats = $conn->query("SELECT 
            u.first_name, u.last_name, u.user_group,
            AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) as avg_grade,
            COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) as grade_count,
            COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) * AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) as ranking_score
            FROM users u
            LEFT JOIN journal j ON u.id = j.student_id
            WHERE u.id = $student_id")->fetch_assoc();
            
        $current_position = getCurrentStudentPosition($conn, $student_id);
    }
    ?>
    <div class="duolingo-container">
        <div class="text-center mb-4">
            <h1 class="display-4 fw-bold">🏆 Рейтинг студентов</h1>
            <p class="lead">Самые успешные студенты платформы Digi-mimi</p>
        </div>
        
        <div class="ranking-container">
            <div class="ranking-podium">
                <?php if (!empty($ranking_data)): ?>
                    <?php for ($i = 0; $i < min(5, count($ranking_data)); $i++): 
                        $student = $ranking_data[$i];
                        $podium_class = '';
                        $place_text = '';
                        
                        switch ($student['rank']) {
                            case 1: $podium_class = 'podium-1'; $place_text = '🥇'; break;
                            case 2: $podium_class = 'podium-2'; $place_text = '🥈'; break;
                            case 3: $podium_class = 'podium-3'; $place_text = '🥉'; break;
                            case 4: $podium_class = 'podium-4'; $place_text = '4'; break;
                            case 5: $podium_class = 'podium-5'; $place_text = '5'; break;
                        }
                    ?>
                    <div class="podium-item <?= $podium_class ?>">
                        <div class="podium-place"><?= $place_text ?></div>
                        <div class="podium-avatar" style="background: linear-gradient(135deg, 
                            <?= $student['rank'] == 1 ? 'var(--gold)' : ($student['rank'] == 2 ? 'var(--silver)' : 'var(--bronze)') ?>, 
                            <?= $student['rank'] == 1 ? '#FFEC8B' : ($student['rank'] == 2 ? '#E8E8E8' : '#E8B886') ?>);">
                            <?= substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1) ?>
                        </div>
                        <div class="podium-bar"></div>
                        <div class="podium-info">
                            <div class="podium-name"><?= $student['first_name'] . ' ' . $student['last_name'] ?></div>
                            <div class="podium-score">Ср. балл: <?= round($student['avg_grade'], 1) ?></div>
                            <div class="podium-score">Группа: <?= $student['user_group'] ?></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                <?php else: ?>
                    <div class="text-center w-100">
                        <div class="icon-large">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Нет данных для рейтинга</h4>
                        <p class="text-muted">Оценки еще не выставлены</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($_SESSION['role'] == 'student' && $current_student_stats && $current_student_stats['avg_grade']): ?>
            <div class="ranking-stats">
                <h4 class="text-center mb-4">📊 Ваша статистика</h4>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Ваше место в рейтинге:</h6>
                        <h2 class="text-center <?= $current_position <= 3 ? 'text-warning' : '' ?>">
                            <?= $current_position ? $current_position . '-е' : 'Не в рейтинге' ?>
                        </h2>
                    </div>
                    <div class="col-md-6">
                        <h6>Ваш средний балл:</h6>
                        <h2 class="text-center"><?= round($current_student_stats['avg_grade'], 1) ?></h2>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Прогресс к следующему уровню:</h6>
                    <div class="xp-bar">
                        <div class="xp-fill" data-width="<?= min(($current_student_stats['grade_count'] * 10), 100) ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span>Оценок: <?= $current_student_stats['grade_count'] ?></span>
                        <span><?= min(($current_student_stats['grade_count'] * 10), 100) ?>%</span>
                    </div>
                </div>
                
                <?php if ($current_position && $current_position > 5): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Вы находитесь на <?= $current_position ?>-м месте. 
                    Для попадания в топ-5 нужно улучшить средний балл до <?= 
                        !empty($ranking_data) && isset($ranking_data[4]) ? 
                        round($ranking_data[4]['avg_grade'] + 0.1, 1) : '4.0' ?>.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($ranking_data)): ?>
        <div class="row mt-5">
            <div class="col-md-4">
                <div class="card bg-white bg-opacity-10 border-0">
                    <div class="card-body text-center">
                        <div class="icon-large">
                            <i class="fas fa-star"></i>
                        </div>
                        <h5>Система оценки</h5>
                        <p>Рейтинг рассчитывается на основе среднего балла и количества оценок</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-white bg-opacity-10 border-0">
                    <div class="card-body text-center">
                        <div class="icon-large">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <h5>Ежедневное обновление</h5>
                        <p>Рейтинг обновляется автоматически после каждой новой оценки</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-white bg-opacity-10 border-0">
                    <div class="card-body text-center">
                        <div class="icon-large">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h5>Мотивация</h5>
                        <p>Попадание в топ-5 дает дополнительные преимущества</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

function includeAssignmentsPage() {
    global $conn;
    
    if ($_SESSION['role'] == 'teacher' || $_SESSION['role'] == 'director') {
        ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-tasks"></i> Задания для студентов</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-custom">
                                <thead>
                                    <tr>
                                        <th>Студент</th>
                                        <th>Предмет</th>
                                        <th>Тип задания</th>
                                        <th>Задание</th>
                                        <th>Дедлайн</th>
                                        <th>Статус</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($_SESSION['role'] == 'teacher') {
                                        $assignments = $conn->query("SELECT a.*, u.first_name, u.last_name, s.name as subject_name 
                                                                    FROM assignments a 
                                                                    JOIN users u ON a.student_id = u.id 
                                                                    JOIN subjects s ON a.subject_id = s.id 
                                                                    WHERE a.teacher_id = {$_SESSION['user_id']} 
                                                                    ORDER BY a.deadline");
                                    } else {
                                        $assignments = $conn->query("SELECT a.*, u.first_name, u.last_name, s.name as subject_name 
                                                                    FROM assignments a 
                                                                    JOIN users u ON a.student_id = u.id 
                                                                    JOIN subjects s ON a.subject_id = s.id 
                                                                    ORDER BY a.deadline");
                                    }
                                    
                                    while ($assignment = $assignments->fetch_assoc()): 
                                        $is_overdue = strtotime($assignment['deadline']) < time();
                                        $status_class = '';
                                        
                                        if ($assignment['status'] == 'выполнено') {
                                            $status_class = 'success';
                                        } elseif ($is_overdue) {
                                            $status_class = 'danger';
                                        } else {
                                            $status_class = 'warning';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $assignment['first_name'] . ' ' . $assignment['last_name'] ?></td>
                                        <td><?= $assignment['subject_name'] ?></td>
                                        <td><span class="badge bg-<?= $assignment['task_type'] == 'Домашнее задание' ? 'info' : 'warning' ?>"><?= $assignment['task_type'] ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($assignment['title']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($assignment['description'], 0, 100)) ?>...</small>
                                        </td>
                                        <td>
                                            <?= date('d.m.Y H:i', strtotime($assignment['deadline'])) ?>
                                            <?php if ($is_overdue && $assignment['status'] != 'выполнено'): ?>
                                            <br><span class="badge bg-danger">Просрочено!</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?>">
                                                <?= $assignment['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($_SESSION['role'] == 'director'): ?>
                                            <button class="btn btn-sm btn-danger" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Дать новое задание</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Студент</label>
                                <select class="form-control form-control-custom" id="student_id" name="student_id" required>
                                    <option value="">Выберите студента...</option>
                                    <?php
                                    $students = $conn->query("SELECT * FROM users WHERE role = 'student' ORDER BY last_name, first_name");
                                    while ($student = $students->fetch_assoc()): ?>
                                    <option value="<?= $student['id'] ?>"><?= $student['last_name'] . ' ' . $student['first_name'] ?> (<?= $student['user_group'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject_id" class="form-label">Предмет</label>
                                <select class="form-control form-control-custom" id="subject_id" name="subject_id" required>
                                    <option value="">Выберите предмет...</option>
                                    <?php
                                    $subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
                                    while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="task_type" class="form-label">Тип задания</label>
                                <select class="form-control form-control-custom" id="task_type" name="task_type" required>
                                    <option value="Домашнее задание">Домашнее задание</option>
                                    <option value="СРС">СРС (Самостоятельная работа)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Название задания</label>
                                <input type="text" class="form-control form-control-custom" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Описание задания</label>
                                <textarea class="form-control form-control-custom" id="description" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deadline" class="form-label">Дедлайн</label>
                                <input type="datetime-local" class="form-control form-control-custom" id="deadline" name="deadline" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="add_assignment" class="btn btn-primary-custom">
                                    <i class="fas fa-paper-plane"></i> Создать задание
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        // Страница заданий для студента
        $student_id = $_SESSION['user_id'];
        $assignments = $conn->query("SELECT a.*, s.name as subject_name, u.first_name, u.last_name 
                                    FROM assignments a 
                                    JOIN subjects s ON a.subject_id = s.id 
                                    JOIN users u ON a.teacher_id = u.id 
                                    WHERE a.student_id = $student_id 
                                    ORDER BY a.deadline");
        
        $assignments_stats = getStudentAssignmentsCount($conn, $student_id);
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-tasks"></i> Мои задания</h4>
                        <?php if ($assignments_stats['active'] > 0): ?>
                        <span class="badge bg-danger pulse">Активных заданий: <?= $assignments_stats['active'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($assignments->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-custom">
                                <thead>
                                    <tr>
                                        <th>Предмет</th>
                                        <th>Преподаватель</th>
                                        <th>Тип задания</th>
                                        <th>Задание</th>
                                        <th>Дедлайн</th>
                                        <th>Статус</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($assignment = $assignments->fetch_assoc()): 
                                        $is_overdue = strtotime($assignment['deadline']) < time();
                                        $status_class = '';
                                        $status_text = $assignment['status'];
                                        
                                        if ($assignment['status'] == 'выполнено') {
                                            $status_class = 'success';
                                        } elseif ($is_overdue) {
                                            $status_class = 'danger';
                                            $status_text = 'просрочено';
                                        } else {
                                            $status_class = 'warning';
                                            $status_text = 'активно';
                                        }
                                    ?>
                                    <tr class="<?= $is_overdue && $assignment['status'] != 'выполнено' ? 'table-danger' : '' ?>">
                                        <td><?= $assignment['subject_name'] ?></td>
                                        <td><?= $assignment['first_name'] . ' ' . $assignment['last_name'] ?></td>
                                        <td><span class="badge bg-<?= $assignment['task_type'] == 'Домашнее задание' ? 'info' : 'warning' ?>"><?= $assignment['task_type'] ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($assignment['title']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($assignment['description']) ?></small>
                                        </td>
                                        <td>
                                            <?= date('d.m.Y H:i', strtotime($assignment['deadline'])) ?>
                                            <?php if ($is_overdue && $assignment['status'] != 'выполнено'): ?>
                                            <br><span class="badge bg-danger">Просрочено!</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?>">
                                                <?= $status_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($assignment['status'] == 'активно'): ?>
                                            <button class="btn btn-sm btn-success" title="Отметить как выполненное">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Статистика заданий -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card bg-primary bg-opacity-10">
                                    <div class="card-body text-center">
                                        <h5 class="text-primary"><?= $assignments_stats['total'] ?></h5>
                                        <small>Всего заданий</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success bg-opacity-10">
                                    <div class="card-body text-center">
                                        <h5 class="text-success"><?= $assignments_stats['active'] ?></h5>
                                        <small>Активных</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger bg-opacity-10">
                                    <div class="card-body text-center">
                                        <h5 class="text-danger"><?= $assignments_stats['overdue'] ?></h5>
                                        <small>Просрочено</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary bg-opacity-10">
                                    <div class="card-body text-center">
                                        <h5 class="text-secondary"><?= $assignments_stats['completed'] ?></h5>
                                        <small>Выполнено</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center p-5">
                            <div class="icon-large">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h5>Нет активных заданий</h5>
                            <p class="text-muted">Все задания выполнены! Преподаватели пока не добавили новых заданий.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

function includeProfilePage() {
    global $conn;
    
    if ($_SESSION['role'] == 'student') {
        $student_id = $_SESSION['user_id'];
        $assignments_stats = getStudentAssignmentsCount($conn, $student_id);
        $avg_grade = getStudentAverageGrade($conn, $student_id);
        $current_position = getCurrentStudentPosition($conn, $student_id);
        
        // Получаем последние оценки
        $recent_grades = $conn->query("SELECT j.*, s.name as subject_name 
                                      FROM journal j 
                                      JOIN subjects s ON j.subject_id = s.id 
                                      WHERE j.student_id = $student_id AND j.grade != 'НБ'
                                      ORDER BY j.date DESC 
                                      LIMIT 5");
        
        // Получаем предметы с лучшими и худшими оценками
        $subject_stats = $conn->query("SELECT s.name, 
                                      AVG(CASE WHEN j.grade != 'НБ' THEN CAST(j.grade AS UNSIGNED) ELSE NULL END) as avg_grade,
                                      COUNT(CASE WHEN j.grade != 'НБ' THEN 1 END) as grade_count
                                      FROM journal j 
                                      JOIN subjects s ON j.subject_id = s.id 
                                      WHERE j.student_id = $student_id 
                                      GROUP BY s.id 
                                      HAVING avg_grade IS NOT NULL 
                                      ORDER BY avg_grade DESC");
        
        $best_subject = $subject_stats->num_rows > 0 ? $subject_stats->fetch_assoc() : null;
        $subject_stats->data_seek(0); // Сбрасываем указатель
        
        $all_subjects = [];
        while ($subject = $subject_stats->fetch_assoc()) {
            $all_subjects[] = $subject;
        }
        
        $worst_subject = !empty($all_subjects) ? end($all_subjects) : null;
    }
    ?>
    <div class="row">
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-user-circle"></i> Мой профиль</h4>
                </div>
                <div class="card-body text-center">
                    <div class="icon-large">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h4><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></h4>
                    <p class="text-muted"><?= $_SESSION['role'] == 'student' ? 'Студент' : ($_SESSION['role'] == 'teacher' ? 'Преподаватель' : 'Директор') ?></p>
                    
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">
                            <strong>Группа:</strong> <?= $_SESSION['group'] ?>
                        </div>
                        <div class="list-group-item">
                            <strong>Роль:</strong> <?= $_SESSION['role'] ?>
                        </div>
                        <div class="list-group-item">
                            <strong>Логин:</strong> <?= $_SESSION['username'] ?>
                        </div>
                        <div class="list-group-item">
                            <strong>Год рождения:</strong> <?= $_SESSION['birth_year'] ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($_SESSION['role'] == 'student'): ?>
            <div class="card card-custom mt-3">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-trophy"></i> Достижения</h4>
                </div>
                <div class="card-body">
                    <?php if ($current_position && $current_position <= 5): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-crown"></i> <strong>Вы в топ-<?= $current_position ?>!</strong>
                        <p class="mb-0 small">Поздравляем! Вы вошли в список лучших студентов платформы.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($best_subject): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-star"></i> <strong>Лучший предмет:</strong>
                        <p class="mb-0 small"><?= $best_subject['name'] ?> (средний балл: <?= round($best_subject['avg_grade'], 1) ?>)</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-8">
            <?php if ($_SESSION['role'] == 'student'): ?>
            <div class="card card-custom">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-chart-bar"></i> Статистика успеваемости</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="p-3 bg-primary bg-opacity-10 rounded">
                                <h2 class="text-primary"><?= $avg_grade ?: '0.0' ?></h2>
                                <small>Средний балл</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                <h2 class="text-success"><?= $assignments_stats['active'] ?></h2>
                                <small>Активных заданий</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                <h2 class="text-warning"><?= $current_position ? $current_position . '-е' : 'Нет' ?></h2>
                                <small>Место в рейтинге</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Последние оценки:</h5>
                            <?php if ($recent_grades->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($grade = $recent_grades->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= $grade['subject_name'] ?></strong><br>
                                        <small><?= date('d.m.Y', strtotime($grade['date'])) ?></small>
                                    </div>
                                    <span class="badge bg-<?= $grade['grade'] >= 4 ? 'success' : ($grade['grade'] >= 3 ? 'warning' : 'danger') ?>">
                                        <?= $grade['grade'] ?>
                                    </span>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">Пока нет оценок</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Прогресс по предметам:</h5>
                            <?php if (!empty($all_subjects)): ?>
                                <?php foreach ($all_subjects as $subject): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?= $subject['name'] ?></span>
                                        <span><?= round($subject['avg_grade'], 1) ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" style="width: <?= ($subject['avg_grade'] / 5) * 100 ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">Нет данных по предметам</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card card-custom">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-chart-bar"></i> Общая статистика</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="p-3 bg-primary bg-opacity-10 rounded">
                                <?php
                                $total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
                                ?>
                                <h2 class="text-primary"><?= $total_students ?></h2>
                                <small>Всего студентов</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                <?php
                                $total_subjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
                                ?>
                                <h2 class="text-success"><?= $total_subjects ?></h2>
                                <small>Предметов</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                <?php
                                $total_assignments = $conn->query("SELECT COUNT(*) as count FROM assignments")->fetch_assoc()['count'];
                                ?>
                                <h2 class="text-warning"><?= $total_assignments ?></h2>
                                <small>Всего заданий</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Как <?= $_SESSION['role'] == 'teacher' ? 'преподаватель' : 'директор' ?>, вы имеете доступ к:
                        <ul class="mb-0 mt-2">
                            <li>Журналу оценок</li>
                            <li>Расписанию занятий</li>
                            <li>Созданию заданий для студентов</li>
                            <?php if ($_SESSION['role'] == 'director'): ?>
                            <li>Административной панели</li>
                            <li>Управлению предметами</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function includeAdminPage() {
    global $conn;
    
    if ($_SESSION['role'] == 'director') {
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-cog"></i> Панель администратора</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card card-custom mb-4">
                                    <div class="card-header card-header-custom">
                                        <h5 class="mb-0">Добавить новый предмет</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Название предмета</label>
                                                <input type="text" class="form-control form-control-custom" id="name" name="name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Описание</label>
                                                <textarea class="form-control form-control-custom" id="description" name="description" rows="3"></textarea>
                                            </div>
                                            <button type="submit" name="add_subject" class="btn btn-primary-custom">Добавить предмет</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card card-custom mb-4">
                                    <div class="card-header card-header-custom">
                                        <h5 class="mb-0">Статистика системы</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $stats = [
                                            'students' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'],
                                            'teachers' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'],
                                            'subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'],
                                            'assignments' => $conn->query("SELECT COUNT(*) as count FROM assignments")->fetch_assoc()['count'],
                                            'active_assignments' => $conn->query("SELECT COUNT(*) as count FROM assignments WHERE status = 'активно' AND deadline > NOW()")->fetch_assoc()['count']
                                        ];
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Студентов:</span>
                                                <strong><?= $stats['students'] ?></strong>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Преподавателей:</span>
                                                <strong><?= $stats['teachers'] ?></strong>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Предметов:</span>
                                                <strong><?= $stats['subjects'] ?></strong>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Всего заданий:</span>
                                                <strong><?= $stats['assignments'] ?></strong>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Активных заданий:</span>
                                                <strong class="text-danger"><?= $stats['active_assignments'] ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card card-custom">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0">Управление пользователями</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Имя</th>
                                                <th>Фамилия</th>
                                                <th>Группа</th>
                                                <th>Роль</th>
                                                <th>Дата регистрации</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $users = $conn->query("SELECT * FROM users ORDER BY role, last_name, first_name");
                                            while ($user = $users->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $user['first_name'] ?></td>
                                                <td><?= $user['last_name'] ?></td>
                                                <td><?= $user['user_group'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $user['role'] == 'student' ? 'primary' : ($user['role'] == 'teacher' ? 'success' : 'warning') ?>">
                                                        <?= $user['role'] == 'student' ? 'Студент' : ($user['role'] == 'teacher' ? 'Преподаватель' : 'Директор') ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-lock"></i> Доступ запрещен</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="icon-large text-danger">
                            <i class="fas fa-ban"></i>
                        </div>
                        <h5 class="mt-3">Административная панель доступна только директорам</h5>
                        <p class="text-muted">У вас нет прав для доступа к этой странице</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Закрытие соединения с базой данных
$conn->close();
?>