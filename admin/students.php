<?php
/**
 * OnBolt Digital Experience Platform - Students Roster Management
 * URL: /admin/students.php
 */

declare(strict_types=1);

$adminActive = 'students';
$adminTitle = 'Enrolled Student Roster Management';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $schoolId = (int)$_POST['school_id'];
        $parentId = (int)$_POST['parent_id'];
        $admissionNo = trim($_POST['admission_no']);
        $studentName = trim($_POST['student_name']);
        $gradeClass = trim($_POST['grade_class']);
        $section = trim($_POST['section'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $relation = trim($_POST['relationship'] ?? 'Parent');

        if (empty($admissionNo) || empty($studentName) || empty($gradeClass)) {
            $err = 'Admission Number, Student Name and Grade/Class are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO students (school_id, admission_no, student_name, grade_class, section, gender, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$schoolId, $admissionNo, $studentName, $gradeClass, $section, $gender]);
                $stuId = (int)$pdo->lastInsertId();

                if ($parentId > 0) {
                    $lStmt = $pdo->prepare("INSERT INTO parent_students (parent_id, student_id, relationship, created_at) VALUES (?, ?, ?, NOW())");
                    $lStmt->execute([$parentId, $stuId, $relation]);
                }

                $msg = "Student '{$studentName}' enrolled successfully!";
                logActivity('create_student', 'students', (int)$admin['id'], 'admin', "Enrolled student {$studentName} (#{$stuId})");
            } catch (Exception $e) {
                $err = 'Error enrolling student: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM parent_students WHERE student_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
            $msg = 'Student record deleted.';
            logActivity('delete_student', 'students', (int)$admin['id'], 'admin', "Deleted student #{$id}");
        } catch (Exception $e) {
            $err = 'Cannot delete student with active orders.';
        }
    }
}

$schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
$parents = $pdo->query("SELECT id, login_id, parent_name, school_id FROM parents ORDER BY parent_name ASC")->fetchAll();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT st.*, sc.name as school_name, p.parent_name, p.login_id, ps.relationship 
        FROM students st
        LEFT JOIN schools sc ON st.school_id = sc.id
        LEFT JOIN parent_students ps ON st.id = ps.student_id
        LEFT JOIN parents p ON ps.parent_id = p.id
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (st.student_name LIKE " . $pdo->quote("%{$search}%") . " 
              OR st.admission_no LIKE " . $pdo->quote("%{$search}%") . " 
              OR p.parent_name LIKE " . $pdo->quote("%{$search}%") . ")";
}
$sql .= " ORDER BY st.id DESC";
$studentsList = $pdo->query($sql)->fetchAll();
?>

<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ⚠️ <?= sanitize($err) ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2.5rem; align-items:flex-start;">
    
    <!-- Student List -->
    <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 class="heading-2" style="font-size:1.3rem;">Active Student Roster (<?= count($studentsList) ?>)</h3>
            
            <form action="" method="GET" style="display:flex; gap:0.5rem;">
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Enter your Student Name or Admission Number" class="form-control" style="padding:0.4rem 0.6rem; font-size:0.85rem; width:180px;">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>
        </div>

        <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Adm No</th>
                        <th>Student Name</th>
                        <th>Grade / Class</th>
                        <th>School</th>
                        <th>Parent / Guardian</th>
                        <th>Gender</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentsList)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--brand-muted);">No student records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($studentsList as $st): ?>
                            <tr>
                                <td style="font-family:monospace; font-weight:800; color:var(--brand-primary);"><?= sanitize($st['admission_no']) ?></td>
                                <td style="font-weight:700; color:var(--brand-dark);"><?= sanitize($st['student_name']) ?></td>
                                <td style="font-weight:600;"><?= sanitize($st['grade_class']) ?> <?= !empty($st['section']) ? '- ' . sanitize($st['section']) : '' ?></td>
                                <td style="font-size:0.85rem;"><?= sanitize($st['school_name'] ?: 'School') ?></td>
                                <td>
                                    <?php if ($st['parent_name']): ?>
                                        <div style="font-weight:600;"><?= sanitize($st['parent_name']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--brand-muted);">ID: <?= sanitize($st['login_id']) ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--brand-muted); font-size:0.8rem;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($st['gender']) ?></td>
                                <td style="text-align:right;">
                                    <form action="" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($st['full_name']) ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $st['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--brand-accent); padding:0.25rem 0.5rem; font-size:0.75rem;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Enroll Student Form -->
    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
        <h3 class="heading-2" style="font-size:1.2rem; margin-bottom:1.25rem;">Enroll New Student</h3>

        <form action="" method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">School Institution *</label>
                <select name="school_id" required class="form-control">
                    <?php foreach ($schools as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Parent Account *</label>
                <select name="parent_id" required class="form-control">
                    <option value="0">Select Parent</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['parent_name']) ?> (ID: <?= sanitize($p['login_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Admission Number *</label>
                <input type="text" name="admission_no" required class="form-control" placeholder="Enter your Admission Number">
            </div>

            <div class="form-group">
                <label class="form-label">Student Full Name *</label>
                <input type="text" name="student_name" required class="form-control" placeholder="Enter your Student Name">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div class="form-group">
                    <label class="form-label">Class / Grade *</label>
                    <input type="text" name="grade_class" required class="form-control" placeholder="Enter your Class">
                </div>
                <div class="form-group">
                    <label class="form-label">Section</label>
                    <input type="text" name="section" class="form-control" placeholder="Enter your Section">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Relationship</label>
                    <input type="text" name="relationship" value="Parent" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">
                Enroll Student in Portal
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
