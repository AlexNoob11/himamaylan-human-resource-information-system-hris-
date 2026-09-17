<?php
ob_start(); // optional but recommended

session_start();
require_once 'conn.php';

// Define isAdmin function
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Define document categories
$document_categories = [
    'Required Documents' => ['SSS Form', 'PhilHealth Form', 'PagIBIG Form', 'TIN Form', 'Birth Certificate'],
    'Educational Documents' => ['Transcript of Records', 'Diploma', 'Certificates', 'Training Certificates'],
    'Employment Documents' => ['Resume/CV', 'Application Form', 'Employment Contract', 'Performance Evaluation', 'Job Offer Letter'],
    'Clearance Documents' => ['NBI Clearance', 'Police Clearance', 'Barangay Clearance', 'Medical Certificate'],
    'Identification Documents' => ['Passport', 'Driver\'s License', 'UMID', 'Postal ID', 'Voter\'s ID'],
    'Other Documents' => ['Other']
];

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['employee_id'])) {
    // Handle multiple file uploads
    if(isset($_FILES['document_files'])) {
        $employee_id = intval($_POST['employee_id']);
        $document_category = $_POST['document_category'];
        $document_type = $_POST['document_type'];
        $document_name = $_POST['document_name'];
        $notes = $_POST['notes'] ?? '';
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : NULL;
        
        $upload_count = 0;
        $error_messages = [];
        
        // File upload handling
        $target_dir = "uploads/documents/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Handle multiple files
        $file_count = count($_FILES['document_files']['name']);
        
        for($i = 0; $i < $file_count; $i++) {
            if($_FILES['document_files']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = basename($_FILES["document_files"]["name"][$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_file_name = "doc_" . time() . "_" . uniqid() . "_" . $i . "." . $file_ext;
                $target_file = $target_dir . $new_file_name;
                
                // Validate file
                $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $max_size = 10 * 1024 * 1024; // 10MB
                
                if (!in_array($file_ext, $allowed_types)) {
                    $error_messages[] = "File '{$file_name}' type not allowed. Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG.";
                } elseif ($_FILES["document_files"]["size"][$i] > $max_size) {
                    $error_messages[] = "File '{$file_name}' is too large. Maximum size is 10MB.";
                } else {
                    if (move_uploaded_file($_FILES["document_files"]["tmp_name"][$i], $target_file)) {
                        // Insert document record
                        $sql = "INSERT INTO employee_documents (employee_id, document_category, document_type, document_name, file_path, notes, expiration_date, upload_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("issssss", $employee_id, $document_category, $document_type, $document_name, $target_file, $notes, $expiration_date);
                        
                        if ($stmt->execute()) {
                            $upload_count++;
                        } else {
                            $error_messages[] = "Error saving document '{$file_name}': " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error_messages[] = "Error uploading file '{$file_name}'.";
                    }
                }
            } elseif($_FILES['document_files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $error_messages[] = "Error with file upload: " . $_FILES['document_files']['error'][$i];
            }
        }
        
        if($upload_count > 0) {
            $_SESSION['success'] = "Successfully uploaded {$upload_count} document(s).";
            if(!empty($error_messages)) {
                $_SESSION['warning'] = implode("<br>", $error_messages);
            }
        } elseif(!empty($error_messages)) {
            $_SESSION['error'] = implode("<br>", $error_messages);
        } else {
            $_SESSION['error'] = "No files were uploaded.";
        }
        
        // Redirect to prevent form resubmission
        header("Location: 201_file.php");
        exit();
    }
}

$page_title = "Employee 201 Files";

include 'theme/navbar.php';
include 'theme/sidebar.php';

// Initialize variables
$total_records = 0;
$dept_result = null;
$complete_count = 0;
$doc_count = 0;

// Build base query for counting from users table
$count_base_sql = "SELECT COUNT(DISTINCT u.id) as total FROM users u
                   LEFT JOIN employee_documents d ON u.id = d.employee_id
                   WHERE 1=1";
$conditions = [];
$params = [];
$types = "";

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

if(isset($_GET['department']) && !empty($_GET['department'])) {
    $conditions[] = "u.department_id = ?";
    $params[] = $_GET['department'];
    $types .= "i";
}

if(isset($_GET['status']) && !empty($_GET['status'])) {
    $conditions[] = "u.employee_type = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

if(count($conditions) > 0) {
    $count_base_sql .= " AND " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($count_base_sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];

// Get departments for dropdown
$dept_query = "SELECT id, department_name FROM departments ORDER BY department_name";
$dept_result = $conn->query($dept_query);

// Get employee types for dropdown
$employee_types_query = "SELECT DISTINCT employee_type FROM users WHERE employee_type IS NOT NULL AND employee_type != '' ORDER BY employee_type";
$employee_types_result = $conn->query($employee_types_query);

// Get complete files count
$complete_sql = "SELECT COUNT(DISTINCT u.id) as complete 
                 FROM users u
                 INNER JOIN employee_documents d1 ON u.id = d1.employee_id 
                 WHERE d1.document_type LIKE '%SSS%'
                 AND EXISTS (SELECT 1 FROM employee_documents d2 WHERE d2.employee_id = u.id AND d2.document_type LIKE '%PhilHealth%')
                 AND EXISTS (SELECT 1 FROM employee_documents d3 WHERE d3.employee_id = u.id AND d3.document_type LIKE '%PagIBIG%')";
$complete_result = $conn->query($complete_sql);
$complete_count = $complete_result->fetch_assoc()['complete'];

// Get document count by category
$doc_count_sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN document_category = 'Required Documents' THEN 1 END) as required_docs,
                    COUNT(CASE WHEN document_category = 'Educational Documents' THEN 1 END) as educational_docs,
                    COUNT(CASE WHEN document_category = 'Employment Documents' THEN 1 END) as employment_docs,
                    COUNT(CASE WHEN document_category = 'Clearance Documents' THEN 1 END) as clearance_docs
                  FROM employee_documents";
$doc_result = $conn->query($doc_count_sql);
$doc_stats = $doc_result->fetch_assoc();
$doc_count = $doc_stats['total'];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Employee 201 Files</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Employee Management</li>
                <li class="breadcrumb-item active">201 Files</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Employee Records <span class="badge bg-primary"><?php echo $total_records; ?> Employees</span></h5>
                        
                        <!-- Filter Section -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="card border">
                                    <div class="card-body py-2">
                                        <form method="GET" class="row g-3">
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                    <input type="text" name="search" class="form-control form-control-sm" 
                                                           placeholder="Search employee..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <select name="department" class="form-select form-select-sm">
                                                    <option value="">All Departments</option>
                                                    <?php
                                                    if($dept_result):
                                                        while($dept = $dept_result->fetch_assoc()):
                                                    ?>
                                                    <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo (isset($_GET['department']) && $_GET['department'] == $dept['id']) ? 'selected' : ''; ?>>
                                                        <?php echo $dept['department_name']; ?>
                                                    </option>
                                                    <?php 
                                                        endwhile;
                                                    endif; 
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <select name="status" class="form-select form-select-sm">
                                                    <option value="">All Types</option>
                                                    <?php
                                                    if($employee_types_result):
                                                        while($type = $employee_types_result->fetch_assoc()):
                                                    ?>
                                                    <option value="<?php echo $type['employee_type']; ?>" 
                                                        <?php echo (isset($_GET['status']) && $_GET['status'] == $type['employee_type']) ? 'selected' : ''; ?>>
                                                        <?php echo $type['employee_type']; ?>
                                                    </option>
                                                    <?php 
                                                        endwhile;
                                                    endif; 
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                                    <i class="bi bi-filter"></i> Filter
                                                </button>
                                            </div>
                                            <div class="col-md-2">
                                                <a href="employee_201.php" class="btn btn-secondary btn-sm w-100">
                                                    <i class="bi bi-x-circle"></i> Clear
                                                </a>
                                            </div>
                                            <?php if(isAdmin()): ?>
                                            <div class="col-md-1">
                                                <a href="export_201_files.php" class="btn btn-success btn-sm w-100" title="Export Data">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Display messages -->
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $_SESSION['error'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $_SESSION['success'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['warning'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <?= $_SESSION['warning'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['warning']); ?>
                        <?php endif; ?>
                        
                        <!-- Employees Table -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered datatable">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Employee ID</th>
                                        <th scope="col">Employee Name</th>
                                        <th scope="col">Position</th>
                                        <th scope="col">Department</th>
                                        <th scope="col">Hired Date</th>
                                        <th scope="col">Address</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Documents</th>
                                        <th scope="col">Last Updated</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Build query with filters
                                    $sql = "SELECT u.*, d.department_name,
                                            COUNT(doc.id) as document_count,
                                            MAX(doc.upload_date) as last_document_update
                                            FROM users u
                                            LEFT JOIN departments d ON u.department_id = d.id
                                            LEFT JOIN employee_documents doc ON u.id = doc.employee_id
                                            WHERE 1=1";
                                    
                                    if(count($conditions) > 0) {
                                        $sql .= " AND " . implode(" AND ", $conditions);
                                    }
                                    
                                    $sql .= " GROUP BY u.id ORDER BY u.last_name, u.first_name";
                                    
                                    // Pagination
                                    $results_per_page = 10;
                                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                    $start_from = ($page - 1) * $results_per_page;
                                    $total_pages = ceil($total_records / $results_per_page);
                                    
                                    // Fetch data with limit
                                    $sql .= " LIMIT ?, ?";
                                    $params[] = $start_from;
                                    $params[] = $results_per_page;
                                    $types .= "ii";
                                    
                                    $stmt = $conn->prepare($sql);
                                    if(!empty($params)) {
                                        $stmt->bind_param($types, ...$params);
                                    }
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    $counter = $start_from + 1;
                                    
                                    if($result->num_rows > 0):
                                        while($employee = $result->fetch_assoc()):
                                            // Get document statistics by category
                                            $doc_stats_sql = "SELECT 
                                                document_category,
                                                COUNT(*) as count
                                                FROM employee_documents 
                                                WHERE employee_id = ?
                                                GROUP BY document_category";
                                            $doc_stmt = $conn->prepare($doc_stats_sql);
                                            $doc_stmt->bind_param("i", $employee['id']);
                                            $doc_stmt->execute();
                                            $doc_stats_result = $doc_stmt->get_result();
                                            $category_counts = [];
                                            while($cat = $doc_stats_result->fetch_assoc()) {
                                                $category_counts[$cat['document_category']] = $cat['count'];
                                            }
                                            
                                            // Get recent documents for quick view
                                            $docs_sql = "SELECT document_category, document_type, document_name, upload_date, file_path 
                                                         FROM employee_documents 
                                                         WHERE employee_id = ? 
                                                         ORDER BY upload_date DESC LIMIT 5";
                                            $docs_stmt = $conn->prepare($docs_sql);
                                            $docs_stmt->bind_param("i", $employee['id']);
                                            $docs_stmt->execute();
                                            $docs_result = $docs_stmt->get_result();
                                    ?>
                                    <tr>
                                        <th scope="row"><?php echo $counter++; ?></th>
                                        <td><strong>EMP-<?php echo str_pad($employee['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                    <span class="text-white fw-bold"><?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($employee['last_name']) . ', ' . htmlspecialchars($employee['first_name']); ?></strong>
                                                    <?php if($employee['middle_initial']): ?>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($employee['middle_initial']); ?></div>
                                                    <?php endif; ?>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($employee['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($employee['employee_type'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <?php if(!empty($employee['date_registered']) && $employee['date_registered'] != '0000-00-00 00:00:00'): ?>
                                                <span class="text-primary">
                                                    <i class="bi bi-calendar-check"></i> 
                                                    <?php echo date('M d, Y', strtotime($employee['date_registered'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($employee['address'])): ?>
                                                <div class="small">
                                                    <?php 
                                                    $address = htmlspecialchars($employee['address']);
                                                    if(strlen($address) > 30) {
                                                        echo '<span title="' . $address . '">' . substr($address, 0, 30) . '...</span>';
                                                    } else {
                                                        echo $address;
                                                    }
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">No address</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $employee_type = $employee['employee_type'] ?? 'Unknown';
                                            $status_class = [
                                                'Employee' => 'bg-success',
                                                'Staff' => 'bg-primary',
                                                'Manager' => 'bg-warning',
                                                'Supervisor' => 'bg-info',
                                                'Admin' => 'bg-danger',
                                                'User' => 'bg-secondary'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $status_class[$employee_type] ?? 'bg-secondary'; ?>">
                                                <?php echo $employee_type; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-folder2-open"></i> 
                                                    <strong><?php echo $employee['document_count']; ?></strong> docs
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 350px;">
                                                    <li><h6 class="dropdown-header">Document Categories</h6></li>
                                                    <?php foreach($document_categories as $category => $types): ?>
                                                        <?php if(isset($category_counts[$category])): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="view_201_file.php?id=<?php echo $employee['id']; ?>&category=<?php echo urlencode($category); ?>">
                                                                <div class="d-flex justify-content-between">
                                                                    <span><?php echo $category; ?></span>
                                                                    <span class="badge bg-primary"><?php echo $category_counts[$category]; ?></span>
                                                                </div>
                                                            </a>
                                                        </li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><h6 class="dropdown-header">Recent Documents</h6></li>
                                                    <?php if($docs_result && $docs_result->num_rows > 0): ?>
                                                        <?php while($doc = $docs_result->fetch_assoc()): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo $doc['file_path']; ?>" target="_blank">
                                                                <div>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                                                    <small class="text-muted">
                                                                        <?php echo htmlspecialchars($doc['document_type']); ?> • 
                                                                        <?php echo $doc['document_category']; ?> • 
                                                                        <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                                    </small>
                                                                </div>
                                                            </a>
                                                        </li>
                                                        <?php endwhile; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-center text-primary" href="view_201_file.php?id=<?php echo $employee['id']; ?>">
                                                            <i class="bi bi-eye"></i> View All Documents
                                                        </a></li>
                                                    <?php else: ?>
                                                        <li><a class="dropdown-item text-center text-muted" href="#">
                                                            <i class="bi bi-folder-x"></i> No documents yet
                                                        </a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($employee['last_document_update']): ?>
                                                <span class="text-success">
                                                    <i class="bi bi-clock-history"></i> 
                                                    <?php echo date('M d, Y', strtotime($employee['last_document_update'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No documents</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="uploadDocument(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>')" 
                                                        title="Upload Documents" data-bs-toggle="tooltip">
                                                    <i class="bi bi-cloud-upload"></i>
                                                </button>
                                                <a href="view_201_file.php?id=<?php echo $employee['id']; ?>" 
                                                   class="btn btn-primary btn-sm" title="View 201 File" data-bs-toggle="tooltip">
                                                    <i class="bi bi-folder2"></i>
                                                </a>
                                                
                                                <a href="employee_timeline.php?id=<?php echo $employee['id']; ?>" 
                                                   class="btn btn-info btn-sm" title="Timeline" data-bs-toggle="tooltip">
                                                    <i class="bi bi-clock-history"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else: 
                                    ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="bi bi-folder-x display-6"></i>
                                                <h5 class="mt-3">No employees found</h5>
                                                <p>Try adjusting your search filters or check if you have users in the database.</p>
                                                <a href="employee_201.php" class="btn btn-primary mt-2">Clear All Filters</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Enhanced Statistics Cards -->
                        <div class="row mt-4">
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Employees</h5>
                                                <h2 class="mb-0"><?php echo $total_records; ?></h2>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">All departments</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Complete Files</h5>
                                                <h2 class="mb-0"><?php echo $complete_count; ?></h2>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">With all required docs</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Required Docs</h5>
                                                <h2 class="mb-0"><?php echo $doc_stats['required_docs']; ?></h2>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-file-earmark-text text-danger" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">SSS, PhilHealth, PagIBIG</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Educational</h5>
                                                <h2 class="mb-0"><?php echo $doc_stats['educational_docs']; ?></h2>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-mortarboard text-info" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Diplomas, TOR, Certificates</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Clearances</h5>
                                                <h2 class="mb-0"><?php echo $doc_stats['clearance_docs']; ?></h2>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-shield-check text-warning" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">NBI, Police, Medical</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Total Docs</h5>
                                                <h2 class="mb-0"><?php echo $doc_count; ?></h2>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-archive text-secondary" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">All documents</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- End Stats Cards -->
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Documents for <span id="employeeName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="modalEmployeeId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Document Category <span class="text-danger">*</span></label>
                            <select name="document_category" class="form-select" id="documentCategory" required onchange="updateDocumentTypes()">
                                <option value="">Select Category</option>
                                <?php foreach($document_categories as $category => $types): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Document Type <span class="text-danger">*</span></label>
                            <select name="document_type" class="form-select" id="documentType" required>
                                <option value="">Select Type</option>
                            </select>
                            <input type="text" name="custom_document_type" class="form-control mt-2 d-none" id="customDocumentType" placeholder="Enter document type">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Document Name <span class="text-danger">*</span></label>
                        <input type="text" name="document_name" class="form-control" placeholder="e.g., SSS E1 Form, Medical Certificate 2024" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Files <span class="text-danger">*</span></label>
                        <div class="file-upload-area border rounded p-3 text-center">
                            <input type="file" name="document_files[]" class="form-control" id="documentFiles" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <div class="mt-2">
                                <small class="text-muted">You can select multiple files (max 10 files, 10MB each)</small>
                            </div>
                            <div id="filePreview" class="mt-3"></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiration Date (if applicable)</label>
                            <input type="date" name="expiration_date" class="form-control">
                            <small class="text-muted">For documents with expiration (e.g., clearances)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Philippines Police Clearance Authentication</label>
                            <select name="clearance_auth" class="form-select">
                                <option value="">Not Applicable</option>
                                <option value="NBI">National Bureau of Investigation</option>
                                <option value="PNP">Philippine National Police</option>
                                <option value="Local">Local Police Station</option>
                                <option value="DFA">Department of Foreign Affairs</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional information about these documents..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload"></i> Upload Documents
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Document categories and types
const documentCategories = <?php echo json_encode($document_categories); ?>;

function uploadDocument(employeeId, employeeName) {

    document.getElementById('modalEmployeeId').value = employeeId;
    document.getElementById('employeeName').textContent = employeeName;

    const form = document.getElementById('uploadForm');
    form.reset();

    document.getElementById('filePreview').innerHTML = '';

    document.getElementById('documentType').innerHTML =
        '<option value="">Select Type</option>';

    document.getElementById('customDocumentType')
        .classList.add('d-none');

    if (typeof bootstrap !== 'undefined') {

        const modalElement =
            document.getElementById('uploadModal');

        const modal =
            new bootstrap.Modal(modalElement);

        modal.show();

    } else {

        alert(
            "Bootstrap JavaScript is not loaded.\n\n" +
            "Add bootstrap.bundle.min.js to your footer."
        );

    }

}

function updateDocumentTypes() {
    const category = document.getElementById('documentCategory').value;
    const typeSelect = document.getElementById('documentType');
    const customInput = document.getElementById('customDocumentType');
    
    typeSelect.innerHTML = '<option value="">Select Type</option>';
    customInput.classList.add('d-none');
    
    if (category && documentCategories[category]) {
        documentCategories[category].forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            typeSelect.appendChild(option);
        });
        
        // Add "Other" option if not already present
        if (!documentCategories[category].includes('Other')) {
            const otherOption = document.createElement('option');
            otherOption.value = 'Other';
            otherOption.textContent = 'Other';
            typeSelect.appendChild(otherOption);
        }
    }
    
    // If category is "Other Documents", show custom input
    if (category === 'Other Documents') {
        customInput.classList.remove('d-none');
        customInput.required = true;
        typeSelect.required = false;
    } else {
        customInput.required = false;
        typeSelect.required = true;
    }
}

// Handle file selection preview
document.getElementById('documentFiles').addEventListener('change', function(e) {
    const filePreview = document.getElementById('filePreview');
    filePreview.innerHTML = '';
    
    if (this.files.length > 10) {
        alert('Maximum 10 files allowed');
        this.value = '';
        return;
    }
    
    Array.from(this.files).forEach((file, index) => {
        const fileSize = (file.size / (1024 * 1024)).toFixed(2); // MB
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item d-flex justify-content-between align-items-center border-bottom py-2';
        fileItem.innerHTML = `
            <div>
                <i class="bi bi-file-text me-2"></i>
                <span class="file-name">${file.name}</span>
                <small class="text-muted ms-2">(${fileSize} MB)</small>
            </div>
            <small class="text-muted">${file.type || 'Unknown type'}</small>
        `;
        filePreview.appendChild(fileItem);
    });
    
    if (this.files.length > 0) {
        const fileCount = document.createElement('div');
        fileCount.className = 'mt-2 text-center text-primary';
        fileCount.innerHTML = `<strong>${this.files.length} file(s) selected</strong>`;
        filePreview.appendChild(fileCount);
    }
});

// Handle "Other" document type selection
document.getElementById('documentType').addEventListener('change', function() {
    const customInput = document.getElementById('customDocumentType');
    if (this.value === 'Other') {
        customInput.classList.remove('d-none');
        customInput.required = true;
        // Clear the value when selecting "Other"
        customInput.value = '';
    } else {
        customInput.classList.add('d-none');
        customInput.required = false;
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {

    // Initialize Bootstrap tooltips safely
    if (typeof bootstrap !== 'undefined') {

        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');

        tooltipTriggerList.forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

    } else {
        console.warn("Bootstrap JS is not loaded.");
    }

    // Initialize DataTable safely
    if (typeof simpleDatatables !== 'undefined') {
        new simpleDatatables.DataTable(".datatable");
    }

});
</script>

<?php 
if(file_exists('theme/footer.php')) {
    include 'theme/footer.php';
} elseif(file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}