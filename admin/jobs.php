<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Create a new task/job
    if ($_POST['action'] === 'create_job') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);

        if (!empty($title) && !empty($description)) {
            try {
                $stmt = $conn->prepare("INSERT INTO jobs (title, description, status) VALUES (?, ?, 'open')");
                $stmt->execute([$title, $description]);
                $message = "Operational task created successfully!";
            } catch (PDOException $e) {
                $error = "Error creating job: " . $e->getMessage();
            }
        } else {
            $error = "Title and description are required.";
        }
    }

    // 2. Toggle status
    if ($_POST['action'] === 'toggle_status') {
        $job_id = intval($_POST['job_id']);
        $new_status = $_POST['status'] === 'open' ? 'closed' : 'open';

        if ($job_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $job_id]);
                $message = "Task status updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        }
    }

    // 3. Delete job (and all associated applications)
    if ($_POST['action'] === 'delete_job') {
        $job_id = intval($_POST['job_id']);

        if ($job_id > 0) {
            try {
                $conn->beginTransaction();
                // First remove all applications for this job
                $del_apps = $conn->prepare("DELETE FROM job_applications WHERE job_id = ?");
                $del_apps->execute([$job_id]);
                // Then remove the job itself
                $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
                $stmt->execute([$job_id]);
                $conn->commit();
                $message = "Job vacancy and all its applications have been deleted permanently! Users will no longer see this listing.";
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = "Error deleting task: " . $e->getMessage();
            }
        }
    }
}

// Fetch jobs list
try {
    $jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC")->fetchAll(PDO::FETCH_OBJ);
    
    // Calculate counts
    $open_count = 0;
    $closed_count = 0;
    foreach ($jobs as $j) {
        if ($j->status === 'open') {
            $open_count++;
        } else {
            $closed_count++;
        }
    }

    // Fetch applications mapped by job_id
    $applications = [];
    try {
        $apps = $conn->query("SELECT * FROM job_applications ORDER BY created_at ASC")->fetchAll(PDO::FETCH_OBJ);
        foreach ($apps as $a) {
            $applications[$a->job_id][] = $a;
        }
    } catch(PDOException $e) {
        // table might not exist yet
    }
} catch(PDOException $e) {
    $jobs = [];
    $open_count = 0;
    $closed_count = 0;
}

adminHeader('jobs', 'Jobs List');
?>

<div class="page-header">
    <h1>🕒 COOPERATIVE OPERATIONAL TASKS</h1>
</div>

<?php if($message): ?>
    <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:.75rem 1rem; border-radius:6px; margin-bottom: 1.5rem;">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="error-msg" style="margin-bottom: 1.5rem;">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Operational Tasks</div>
        <div class="stat-card-value"><?= count($jobs) ?></div>
    </div>
    <div class="stat-card amber">
        <div class="stat-card-label">Open Chores</div>
        <div class="stat-card-value"><?= $open_count ?></div>
        <div class="stat-card-sub">Require facility attention</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Completed Tasks</div>
        <div class="stat-card-value"><?= $closed_count ?></div>
        <div class="stat-card-sub">Successfully closed</div>
    </div>
</div>

<div class="dashboard-grid">

    <!-- Left: Jobs Checklist -->
    <div class="table-card" style="flex: 2;">
        <div class="table-toolbar">
            <strong>Active Facility Worklist</strong>
        </div>
        <div style="overflow-x: auto;">
        <table class="admin-table" style="min-width: 700px;">
            <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Chore Description</th>
                    <th>Status</th>
                    <th>Registered At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($jobs) > 0): ?>
                <?php foreach($jobs as $j): ?>
                <tr>
                    <td>#<?= str_pad($j->id, 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($j->title) ?></strong><br>
                        <span style="color:#64748b; font-size:0.8rem;"><?= htmlspecialchars($j->description) ?></span>
                    </td>
                    <td>
                        <?php if($j->status === 'open'): ?>
                            <span class="badge badge-pending">⏳ Open</span>
                        <?php else: ?>
                            <span class="badge badge-completed">✅ Occupied</span>
                        <?php endif; ?>
                        
                        <?php if(isset($applications[$j->id])): ?>
                            <div style="margin-top:0.75rem; font-size:0.75rem; color:#475569; line-height:1.4; background:#f1f5f9; padding:0.5rem; border-radius:4px; border:1px solid #e2e8f0; max-height:120px; overflow-y:auto;">
                                <strong style="color:#0f172a;">Applicants (<?= count($applications[$j->id]) ?>):</strong>
                                <?php foreach($applications[$j->id] as $app): ?>
                                    <div style="margin-top: 0.25rem; padding-top: 0.25rem; border-top: 1px solid #cbd5e1;">
                                        ✉️ <a href="mailto:<?= htmlspecialchars($app->email) ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($app->email) ?></a><br>
                                        📞 <a href="tel:<?= htmlspecialchars($app->phone) ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($app->phone) ?></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b; font-size:0.75rem;"><?= date('d M Y, H:i', strtotime($j->created_at)) ?></td>
                    <td style="min-width: 120px;">
                        <div style="display:flex; flex-direction:column; gap:0.5rem; align-items:flex-start;">
                            <!-- Toggle Status Form -->
                            <form method="POST" style="display:block; width:100%;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="job_id" value="<?= $j->id ?>">
                                <input type="hidden" name="status" value="<?= $j->status ?>">
                                <?php if($j->status === 'open'): ?>
                                    <button type="submit" class="action-link" style="border: 1px solid #dcfce7; background: #f0fdf4; color: #15803d; padding: 0.35rem 0.65rem; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem; width:100%; justify-content:center;">
                                        <span>✔️</span> Complete
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="action-link" style="border: 1px solid #dbeafe; background: #eff6ff; color: #1d4ed8; padding: 0.35rem 0.65rem; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem; width:100%; justify-content:center;">
                                        <span>🔄</span> Reopen
                                    </button>
                                <?php endif; ?>
                            </form>

                            <!-- Delete Form -->
                            <form method="POST" style="display:block; width:100%;" onsubmit="return confirm('⚠️ Delete this job vacancy permanently?\n\nThis will:\n• Remove the job from the members/users vacancy list\n• Delete all applicant registrations for this job\n• Users will no longer be able to apply\n\nThis action cannot be undone.')">
                                <input type="hidden" name="action" value="delete_job">
                                <input type="hidden" name="job_id" value="<?= $j->id ?>">
                                <button type="submit" class="action-link danger" style="border: 1px solid #fee2e2; background: #fef2f2; color: #dc2626; padding: 0.35rem 0.65rem; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem; width:100%; justify-content:center; transition: all 0.2s ease;" onmouseover="this.style.background='#dc2626'; this.style.color='white'" onmouseout="this.style.background='#fef2f2'; this.style.color='#dc2626'">
                                    <span>🗑️</span> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5"><div class="empty-state"><span class="icon">🕒</span>No operational jobs recorded yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div><!-- end scroll wrapper -->
    </div>

    <!-- Right: Add New Job Form -->
    <div class="table-card" style="padding: 1.5rem; height: fit-content;">
        <h3 style="margin-bottom:1rem; color:#0f172a;">➕ Add Facility Job</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_job">
            
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.875rem;">Job Title</label>
                <input type="text" name="title" placeholder="e.g. Sanitize Pasteurizer Line A" required style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.875rem;">
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.875rem;">Description</label>
                <textarea name="description" placeholder="Specify tasks, cleaning instructions, or run details..." required rows="4" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.875rem; font-family:inherit; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">💾 Save Job</button>
        </form>
    </div>

</div>

<?php adminFooter(); ?>
