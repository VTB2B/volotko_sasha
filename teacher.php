<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$dbFilePath = __DIR__ . '/users.db';

try {
    $pdo = new PDO("sqlite:$dbFilePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получение информации о текущем пользователе
    $stmt = $pdo->prepare("SELECT role, group_id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['user']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'teacher') {
        header('Location: login.php');
        exit();
    }

    $groupId = $user['group_id'];

    // Получение пользователей с тем же group_id, исключая авторизованного
    $stmt = $pdo->prepare("SELECT id, username, password, firstname, lastname, family, created_at FROM users WHERE group_id = :groupId AND username != :currentUser ORDER BY family ASC");
    $stmt->execute([':groupId' => $groupId, ':currentUser' => $_SESSION['user']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $username = htmlspecialchars($_SESSION['user']);

    // AJAX-запрос для инициализации оценок
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'initData') {
        $s = $pdo->prepare("SELECT id, firstname, lastname, family FROM users WHERE group_id = :gid AND role = 'student' ORDER BY family");
        $s->execute([':gid' => $groupId]);
        $students = $s->fetchAll(PDO::FETCH_ASSOC);
        $s2 = $pdo->prepare("SELECT id, name FROM subjects WHERE group_id = :gid ORDER BY name");
        $s2->execute([':gid' => $groupId]);
        $subjects = $s2->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['students' => $students, 'subjects' => $subjects]);
        exit;
    }

    // AJAX-запрос для загрузки студентов
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'loadGrades') {
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, family FROM users WHERE group_id = :gid AND role = 'student' ORDER BY family");
        $stmt->execute([':gid' => $groupId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode(['students' => $students]);
        exit;
    }

} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель куратора</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: #f4f7fa;
        }
        .main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        table td {
            text-align: center;
        }
        .grade-button {
            background: none;
            border: none;
            cursor: pointer;
        }
        .grade-button img {
            transition: transform 0.2s;
        }
        .grade-button:hover img {
            transform: scale(1.1);
        }
        .sidebar {
            width: 250px;
            background-color: #4a74e4;
            color: white;
            padding: 20px;
            height: 100%;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: width 0.3s;
        }
        .collapsed {
            width: 80px;
        }
        .toggle-button {
            background: none;
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .sidebar h2 {
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        .menu-item {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
        }
        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .menu-item img {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            transition: transform 0.3s;
        }
        .content {
            flex-grow: 3;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
        }
        .header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: white;
            padding: 15px;
            border-bottom: 1px solid #e3e6f0;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
        }
        .logout-button {
            padding: 10px 15px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .logout-button:hover {
            background-color: #c82333;
        }
        .welcome-message {
            text-align: center;
            margin: 20px 0;
        }
        .user-table, .subjects-table, .grades-table {
            width: 100%;
            margin-top: 20px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #4a74e4;
            color: white;
            cursor: pointer;
        }
        #addUserForm {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        input[type="text"], input[type="password"], input[type="number"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 80%;
            margin-bottom: 10px;
        }
        .add-user-button {
            padding: 10px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .add-user-button:hover {
            background-color: #218838;
        }
        .delete-button {
            padding: 10px 15px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .delete-button:hover {
            background-color: #c82333;
        }
        .action-buttons {
            margin-top: 10px;
        }
        .action-buttons > button {
            margin-right: 10px;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="main" id="mainContent">
        <div class="sidebar" id="sidebar">
            <h2 id="sidebarTitle">Панель меню</h2>
            <button class="toggle-button" id="toggleButton">
                <img src="pic/menu.png" alt="Toggle Menu" style="width: 20px; height: 20px;">
            </button>
            <div class="menu-item" id="manageUsersButton">
                <img src="pic/icon_1.png" alt="Управление группами">
                <span class="menu-text">Состав группы</span>
            </div>
            <div class="menu-item" id="manageGradesButton">
                <img src="pic/icon_2.png" alt="Успеваемость">
                <span class="menu-text">Успеваемость по группе</span>
            </div>
            <div class="menu-item" id="manageSubjectsButton">
                <img src="pic/icon_5-1.png" alt="Управление учебными предметами">
                <span class="menu-text">Управление учебными предметами</span>
            </div>
        </div>
        <div class="content">
            <div class="header">
                <h1>Вы авторизованы как куратор группы <?= $username ?></h1>
                <button class="logout-button" onclick="window.location.href='logout.php'">Выйти</button>
            </div>
            <div class="welcome-message" id="welcomeMessage">
                <h2>Добро пожаловать на панель куратора!</h2>
                <p>Здесь вы можете управлять составом группы и добавлять пользователей, управлять оценками и предметами.</p>
            </div>

            <div class="user-table" id="userTable" style="display: none;">
                <h2>Состав группы</h2>
                <table>
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Фамилия</th>
                            <th onclick="sortTable(1)">Имя</th>
                            <th onclick="sortTable(2)">Отчество</th>
                            <th onclick="sortTable(3)">Логин</th>
                            <th>Пароль</th>
                            <th onclick="sortTable(4)">Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php foreach ($users as $user): ?>
                        <tr id="user-<?= htmlspecialchars($user['id']) ?>">
                            <td><?= htmlspecialchars($user['family']) ?></td>
                            <td><?= htmlspecialchars($user['firstname']) ?></td>
                            <td><?= htmlspecialchars($user['lastname']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['password']) ?></td>
                            <td><?= date('Y-m-d', strtotime(htmlspecialchars($user['created_at']))) ?></td>
                            <td><button class="delete-button" onclick="deleteUser(<?= htmlspecialchars($user['id']) ?>)">Удалить</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="subjects-table" id="subjectsTable" style="display: none;">
                <h2>Учебные предметы</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Название предмета</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="subjectsTableBody">
                        <!-- Subjects will be loaded here -->
                    </tbody>
                </table>
                <div id="addSubjectForm" style="margin-top: 20px;">
                    <input type="text" id="subjectName" placeholder="Название предмета" required>
                    <button class="add-user-button" id="submitAddSubjectButton">Добавить предмет</button>
                    <p class="message" id="subjectMessage"></p>
                </div>
            </div>
            <!-- Раздел успеваемости -->
            <div class="grades-table" id="gradesSection" style="display:none;">
                <h2>Успеваемость по группе</h2>
                <table id="gradesTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ФИО</th>
                            <th>Сентябрь</th>
                            <th>Октябрь</th>
                            <th>Ноябрь</th>
                            <th>Декабрь</th>
                            <th>1 семестр</th>
                            <th>Январь</th>
                            <th>Февраль</th>
                            <th>Март</th>
                            <th>Апрель</th>
                            <th>Май</th>
                            <th>Июнь</th>
                            <th>2 семестр</th>
                        </tr>
                    </thead>
                    <tbody id="gradesTableBody"></tbody>
                </table>
            </div>
            <div style="margin-top: 20px;" id="addUserButtonContainer">
              <button class="add-user-button" id="addUserButton">Добавить пользователя</button>
            </div>
            <div id="addUserForm" style="display: none; margin-top:20px; text-align:center;">
                <h2>Добавить пользователя</h2>
                <input type="text" id="firstname" placeholder="Имя" required>
                <input type="text" id="lastname" placeholder="Отчество" required>
                <input type="text" id="family" placeholder="Фамилия" required>
                <div class="action-buttons">
                    <button class="add-user-button" id="submitAddUserButton">Добавить</button>
                    <button class="delete-button" onclick="hideAddUserForm()">Отмена</button>
                </div>
                <p class="message" id="userMessage"></p>
            </div>
        </div>
    </div>
    <script>
        const addUserButton = document.getElementById('submitAddUserButton');
        const userMessage = document.getElementById('userMessage');
        const submitAddSubjectButton = document.getElementById('submitAddSubjectButton');
        const subjectMessage = document.getElementById('subjectMessage');

        hideAllSections();
        document.getElementById('welcomeMessage').style.display = 'block'; // Показываем приветственное сообщение

        function hideAllSections() {
            document.getElementById('welcomeMessage').style.display = 'none';
            document.getElementById('userTable').style.display = 'none';
            document.getElementById('subjectsTable').style.display = 'none';
            document.getElementById('gradesSection').style.display = 'none';
            document.getElementById('addUserForm').style.display = 'none';
            document.getElementById('addUserButtonContainer').style.display = 'none';
        }

        function showUserTable() {
            hideAllSections();
            document.getElementById('userTable').style.display = 'block';
            document.getElementById('addUserButtonContainer').style.display = 'block';
            document.getElementById('addUserButton').onclick = showAddUserForm;
        }

        function showSubjectsTable() {
            hideAllSections();
            document.getElementById('subjectsTable').style.display = 'block';
            loadSubjects();
        }

        function showGradesTable() {
            hideAllSections();
            document.getElementById('gradesSection').style.display = 'block';
            loadGrades();
        }

        // Загрузка и управление предметами
        function loadSubjects() {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "load_subjects.php", true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        const tbody = document.getElementById('subjectsTableBody');
                        tbody.innerHTML = '';
                        response.subjects.forEach(sub => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${sub.name}</td>
                                <td><button class="delete-button" onclick="deleteSubject(${sub.id})">Удалить</button></td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else {
                        alert(response.message);
                    }
                }
            };
            xhr.send();
        }

        submitAddSubjectButton.onclick = function() {
            const name = document.getElementById('subjectName').value.trim();
            if (!name) { subjectMessage.textContent = 'Название предмета обязательно'; return; }
            const xhr = new XMLHttpRequest();
            xhr.open('POST','add_subject.php',true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function() {
                const resp = JSON.parse(xhr.responseText);
                if (resp.status==='success') {
                    subjectMessage.textContent = 'Предмет добавлен';
                    loadSubjects(); document.getElementById('subjectName').value='';
                } else {
                    subjectMessage.textContent = resp.message;
                }
            };
            xhr.send('name='+encodeURIComponent(name)+'&groupId='+encodeURIComponent(<?= $groupId ?>));
        };

        function deleteSubject(id) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST','delete_subject.php',true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function() {
                const resp = JSON.parse(xhr.responseText);
                if (resp.status==='success') loadSubjects(); else alert(resp.message);
            };
            xhr.send('id='+id);
        }

        // Управление пользователями
        function showAddUserForm() {
            hideAllSections(); document.getElementById('addUserForm').style.display='block';
        }

        function hideAddUserForm() {
            document.getElementById('addUserForm').style.display = 'none';
            document.getElementById('addUserButtonContainer').style.display = 'block';
            showUserTable(); // Show the user table again
        }

        addUserButton.onclick = function() {
            const f = document.getElementById('firstname').value.trim();
            const l = document.getElementById('lastname').value.trim();
            const fam = document.getElementById('family').value.trim();
            
            if (!f || !l || !fam) { 
                userMessage.textContent = 'Все поля обязательны'; 
                return; 
            }
            
            // Generate a unique username for the new student
            const usernameNew = `${encodeURIComponent('<?= $_SESSION['user'] ?>')}-stud${Math.floor(Math.random() * 1000)}`;
            const passwordNew = Math.random().toString(36).slice(-8);
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'add_student.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                const resp = JSON.parse(xhr.responseText);
                if (resp.status === 'success') {
                    userMessage.textContent = 'Студент добавлен: ' + passwordNew;
                    const row = document.createElement('tr');
                    row.id = 'user-' + resp.id;
                    row.innerHTML = `<td>${fam}</td><td>${f}</td><td>${l}</td><td>${resp.username}</td><td>${passwordNew}</td><td>${new Date().toISOString().slice(0, 10)}</td><td><button class="delete-button" onclick="deleteUser(${resp.id})">Удалить</button></td>`;
                    document.getElementById('userTableBody').appendChild(row);
                } else { 
                    userMessage.textContent = resp.message; 
                }
            };

            // Send the AJAX request with the username
            xhr.send(`username=${encodeURIComponent(usernameNew)}&firstname=${encodeURIComponent(f)}&lastname=${encodeURIComponent(l)}&family=${encodeURIComponent(fam)}&groupId=${encodeURIComponent(<?= $groupId ?>)}`);
        };

        function deleteUser(id) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST','delete_student.php',true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function() {
                const resp = JSON.parse(xhr.responseText);
                if (resp.status==='success') document.getElementById('user-'+id).remove(); else alert(resp.message);
            };
            xhr.send('id='+id);
        }

        function sortTable(idx) {
            const tbl = document.querySelector('table');
            const rows = Array.from(tbl.tBodies[0].rows);
            rows.sort((a,b)=>a.cells[idx].innerText.localeCompare(b.cells[idx].innerText));
            rows.forEach(r=>tbl.tBodies[0].appendChild(r));
        }

        // Sidebar toggle
        const toggleBtn = document.getElementById('toggleButton');
        toggleBtn.onclick = function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelectorAll('.menu-text').forEach(t=>t.style.display = document.getElementById('sidebar').classList.contains('collapsed') ? 'none' : 'inline');
        };

        document.getElementById('manageUsersButton').onclick = showUserTable;
        document.getElementById('manageSubjectsButton').onclick = showSubjectsTable;
        document.getElementById('manageGradesButton').onclick = showGradesTable;
        function handleGradeClick(studentId, subjectId) {
            // Здесь можно добавить логику для обработки клика по иконке
            // Например, открыть модальное окно для ввода оценки
            alert(`Клик по студенту ID: ${studentId}, предмет ID: ${subjectId}`);
        }
        // Загрузка успеваемости
        function loadGrades() {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "?ajax=loadGrades", true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const tbody = document.getElementById('gradesTableBody');
                    tbody.innerHTML = '';

                    response.students.forEach(student => {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${student.family} ${student.firstname} ${student.lastname}</td>`;
                        
                        for (let i = 0; i < 12; i++) {
                            row.innerHTML += `
                                <td>
                                    <button class="grade-button" onclick="handleGradeClick(${student.id}, ${i + 1})">
                                        <img src="pic/icon_5.png" alt="Оценка" style="width: 20px; height: 20px;">
                                    </button>
                                </td>`;
                        }
                        tbody.appendChild(row);
                    });
                }
            };
            xhr.send();
        }

        document.getElementById('manageGradesButton').onclick = showGradesTable;

        // Инициализация
        hideAllSections();
        showUserTable(); // По умолчанию показываем состав группы
    </script>
</body>
</html>