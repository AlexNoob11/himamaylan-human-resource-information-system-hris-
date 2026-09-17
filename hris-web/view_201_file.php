```php
<?php
session_start();
require_once 'conn.php';

$page_title = "Employee 201 File";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Employee ID not specified.";
    header("Location: employee_201.php");
    exit();
}

$employee_id = intval($_GET['id']);
$category_filter = $_GET['category'] ?? '';

include 'theme/navbar.php';
include 'theme/sidebar.php';

/*
|--------------------------------------------------------------------------
| GET EMPLOYEE INFORMATION
|--------------------------------------------------------------------------
*/
$sql = "SELECT u.*, d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Employee not found.";
    header("Location: employee_201.php");
    exit();
}

$employee = $result->fetch_assoc();

/*
|--------------------------------------------------------------------------
| GET DOCUMENTS
|--------------------------------------------------------------------------
*/
if (!empty($category_filter)) {

    $doc_sql = "SELECT *
                FROM employee_documents
                WHERE employee_id = ?
                AND document_category = ?
                ORDER BY upload_date DESC";

    $doc_stmt = $conn->prepare($doc_sql);
    $doc_stmt->bind_param("is", $employee_id, $category_filter);

} else {

    $doc_sql = "SELECT *
                FROM employee_documents
                WHERE employee_id = ?
                ORDER BY document_category, upload_date DESC";

    $doc_stmt = $conn->prepare($doc_sql);
    $doc_stmt->bind_param("i", $employee_id);
}

$doc_stmt->execute();
$documents = $doc_stmt->get_result();

$grouped_documents = [];

while ($doc = $documents->fetch_assoc()) {

    $category = $doc['document_category'] ?: 'Other Documents';

    if (!isset($grouped_documents[$category])) {
        $grouped_documents[$category] = [];
    }

    $grouped_documents[$category][] = $doc;
}
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Employee 201 File</h1>

        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="index.php">Home</a>
                </li>

                <li class="breadcrumb-item">
                    Employee Management
                </li>

                <li class="breadcrumb-item">
                    <a href="employee_201.php">201 Files</a>
                </li>

                <li class="breadcrumb-item active">
                    View Employee
                </li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="row">

            <!-- EMPLOYEE PROFILE -->
            <div class="col-lg-4">

                <div class="card">

                    <div class="card-body text-center">

                        <div
                            class="rounded-circle bg-primary mx-auto mb-3 d-flex align-items-center justify-content-center"
                            style="width:100px;height:100px;font-size:32px;">

                            <span class="text-white fw-bold">
                                <?=
                                strtoupper(
                                    substr($employee['first_name'], 0, 1)
                                    .
                                    substr($employee['last_name'], 0, 1)
                                );
                                ?>
                            </span>

                        </div>

                        <h4>
                            <?= htmlspecialchars($employee['first_name']) ?>
                            <?= htmlspecialchars($employee['middle_initial']) ?>
                            <?= htmlspecialchars($employee['last_name']) ?>
                        </h4>

                        <span class="badge bg-primary">
                            <?= htmlspecialchars($employee['employee_type']) ?>
                        </span>

                        <hr>

                        <table class="table table-borderless text-start">

                            <tr>
                                <th>Email</th>
                                <td><?= htmlspecialchars($employee['email']) ?></td>
                            </tr>

                            <tr>
                                <th>Phone</th>
                                <td><?= htmlspecialchars($employee['phone_number']) ?></td>
                            </tr>

                            <tr>
                                <th>Department</th>
                                <td>
                                    <?= htmlspecialchars($employee['department_name'] ?? 'N/A') ?>
                                </td>
                            </tr>

                            <tr>
                                <th>Birthday</th>
                                <td>
                                    <?= !empty($employee['date_of_birth'])
                                        ? date('M d, Y', strtotime($employee['date_of_birth']))
                                        : 'N/A'
                                    ?>
                                </td>
                            </tr>

                            <tr>
                                <th>Age</th>
                                <td><?= htmlspecialchars($employee['age']) ?></td>
                            </tr>

                            <tr>
                                <th>Status</th>
                                <td><?= htmlspecialchars($employee['civil_status']) ?></td>
                            </tr>

                            <tr>
                                <th>Address</th>
                                <td><?= htmlspecialchars($employee['address']) ?></td>
                            </tr>

                            <tr>
                                <th>Registered</th>
                                <td>
                                    <?= date('M d, Y', strtotime($employee['date_registered'])) ?>
                                </td>
                            </tr>

                        </table>

                    </div>

                </div>

            </div>

            <!-- DOCUMENTS -->
            <div class="col-lg-8">

                <div class="card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="card-title">
                                Employee Documents
                            </h5>

                            <a
                                href="201_file.php"
                                class="btn btn-secondary btn-sm">

                                <i class="bi bi-arrow-left"></i>
                                Back

                            </a>

                        </div>

                        <?php if (empty($grouped_documents)): ?>

                            <div class="text-center py-5">

                                <i
                                    class="bi bi-folder-x"
                                    style="font-size:70px;color:#999;">
                                </i>

                                <h4 class="mt-3">
                                    No Documents Found
                                </h4>

                            </div>

                        <?php else: ?>

                            <?php foreach ($grouped_documents as $category => $docs): ?>

                                <div class="card mb-4 border">

                                    <div class="card-header bg-primary text-white">

                                        <strong>
                                            <?= htmlspecialchars($category) ?>
                                        </strong>

                                        <span class="badge bg-light text-dark float-end">
                                            <?= count($docs) ?>
                                        </span>

                                    </div>

                                    <div class="card-body p-0">

                                        <div class="table-responsive">

                                            <table class="table table-hover mb-0">

                                                <thead class="table-light">

                                                <tr>
                                                    <th>Document Name</th>
                                                    <th>Type</th>
                                                    <th>Uploaded</th>
                                                    <th>Expiration</th>
                                                    <th>Actions</th>
                                                </tr>

                                                </thead>

                                                <tbody>

                                                <?php foreach ($docs as $doc): ?>

                                                    <tr>

                                                        <td>

                                                            <strong>
                                                                <?= htmlspecialchars($doc['document_name']) ?>
                                                            </strong>

                                                            <?php if (!empty($doc['notes'])): ?>

                                                                <div class="small text-muted mt-1">
                                                                    <?= nl2br(htmlspecialchars($doc['notes'])) ?>
                                                                </div>

                                                            <?php endif; ?>

                                                        </td>

                                                        <td>
                                                            <?= htmlspecialchars($doc['document_type']) ?>
                                                        </td>

                                                        <td>
                                                            <?= date(
                                                                'M d, Y',
                                                                strtotime($doc['upload_date'])
                                                            ) ?>
                                                        </td>

                                                        <td>

                                                            <?php if (!empty($doc['expiration_date'])): ?>

                                                                <?php
                                                                $expired =
                                                                    strtotime($doc['expiration_date'])
                                                                    < time();
                                                                ?>

                                                                <span class="badge <?= $expired ? 'bg-danger' : 'bg-success' ?>">

                                                                    <?= date(
                                                                        'M d, Y',
                                                                        strtotime($doc['expiration_date'])
                                                                    ) ?>

                                                                </span>

                                                            <?php else: ?>

                                                                <span class="text-muted">
                                                                    N/A
                                                                </span>

                                                            <?php endif; ?>

                                                        </td>

                                                        <td>

                                                            <?php if (!empty($doc['file_path'])): ?>

                                                                <a
                                                                    href="<?= htmlspecialchars($doc['file_path']) ?>"
                                                                    target="_blank"
                                                                    class="btn btn-primary btn-sm">

                                                                    <i class="bi bi-eye"></i>

                                                                </a>

                                                                <a
                                                                    href="<?= htmlspecialchars($doc['file_path']) ?>"
                                                                    download
                                                                    class="btn btn-success btn-sm">

                                                                    <i class="bi bi-download"></i>

                                                                </a>

                                                            <?php else: ?>

                                                                <span class="text-danger">
                                                                    File Missing
                                                                </span>

                                                            <?php endif; ?>

                                                        </td>

                                                    </tr>

                                                <?php endforeach; ?>

                                                </tbody>

                                            </table>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>

<?php

if (file_exists('theme/footer.php')) {

    include 'theme/footer.php';

} elseif (file_exists('includes/footer.php')) {

    include 'includes/footer.php';

} else {

    echo '</body></html>';
}
?>
```
