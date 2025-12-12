<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('admin');

$message = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        if (!isset($_POST['role'])) {
            $message = '<div class="error">Role parameter is missing.</div>';
        } else {
            $role = sanitize($_POST['role']);
            $table = $role === 'farmer' ? 'farmers' : 'experts';
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $message = '<div class="success">User deleted successfully!</div>';
            } else {
                $message = '<div class="error">Failed to delete user.</div>';
            }
        }
    } elseif (isset($_POST['approve_expert'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE experts SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Expert approved successfully!</div>';
        } else {
            $message = '<div class="error">Failed to approve expert.</div>';
        }
    } elseif (isset($_POST['reject_expert'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM experts WHERE id = ? AND status = 'inactive'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Expert application rejected and user removed.</div>';
        } else {
            $message = '<div class="error">Failed to reject expert.</div>';
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h2>User Management</h2>

    <?php echo $message; ?>

    <div class="view-toggle">
        <button id="separateViewBtn" class="btn active">Separate Views</button>
        <button id="allUsersViewBtn" class="btn">All Users</button>
    </div>

    <div id="separateView" class="user-view">
        <div class="user-section">
            <h4>Farmers</h4>
            <?php
            $stmt = $conn->prepare("SELECT * FROM farmers ORDER BY name ASC");
            $stmt->execute();
            $farmers = $stmt->get_result();
            ?>
            <?php if ($farmers->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($farmer = $farmers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $farmer['name']; ?></td>
                                <td><?php echo $farmer['email']; ?></td>
                                <td><?php echo $farmer['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo $farmer['location'] ?: 'N/A'; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($farmer['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-small" onclick="editUser(<?php echo $farmer['id']; ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to delete this farmer?')">
                                        <input type="hidden" name="user_id" value="<?php echo $farmer['id']; ?>">
                                        <input type="hidden" name="role" value="farmer">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No farmers found.</p>
            <?php endif; ?>
        </div>

        <div class="user-section">
            <h4>Experts</h4>
            <?php
            $stmt = $conn->prepare("SELECT * FROM experts ORDER BY name ASC");
            $stmt->execute();
            $experts = $stmt->get_result();
            ?>
            <?php if ($experts->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($expert = $experts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $expert['name']; ?></td>
                                <td><?php echo $expert['email']; ?></td>
                                <td><?php echo $expert['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo $expert['location'] ?: 'N/A'; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($expert['created_at'])); ?></td>
                                <td><?php echo $expert['status'] === 'active' ? 'Approved' : 'Pending'; ?></td>
                                <td>
                                    <?php if ($expert['status'] === 'inactive'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $expert['id']; ?>">
                                            <button type="submit" name="approve_expert" class="btn btn-small">Approve</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $expert['id']; ?>">
                                            <button type="submit" name="reject_expert" class="btn btn-danger btn-small">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="status-approved">Approved Expert</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No experts found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="allUsersView" class="user-view" style="display: none;">
        <div class="filters">
            <label for="sortBy">Sort by:</label>
            <select id="sortBy">
                <option value="name">Name (A-Z)</option>
                <option value="role">Role</option>
                <option value="created_at">Join Date</option>
            </select>
            <label for="filterRole">Filter by Role:</label>
            <select id="filterRole">
                <option value="">All Roles</option>
                <option value="farmer">Farmer</option>
                <option value="expert">Expert</option>
                <option value="pending_expert">Pending Expert</option>
                <option value="admin">Admin</option>
            </select>
            <label for="filterDate">Join Date:</label>
            <input type="date" id="filterDateFrom">
            <input type="date" id="filterDateTo">
        </div>

        <?php
        // Combine farmers and experts into a single view
        $stmt = $conn->prepare("
            SELECT id, name, email, phone, location, created_at, 'farmer' as role FROM farmers
            UNION ALL
            SELECT id, name, email, phone, location, created_at, 'expert' as role FROM experts
            ORDER BY name ASC
        ");
        $stmt->execute();
        $all_users = $stmt->get_result();
        ?>
        <?php if ($all_users->num_rows > 0): ?>
            <table class="table" id="allUsersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $all_users->fetch_assoc()): ?>
                        <tr data-role="<?php echo $user['role']; ?>" data-joined="<?php echo date('Y-m-d', strtotime($user['created_at'])); ?>">
                            <td><?php echo $user['name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></td>
                            <td><?php echo $user['phone'] ?: 'N/A'; ?></td>
                            <td><?php echo $user['location'] ?: 'N/A'; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['role'] === 'farmer'): ?>
                                    <button class="btn btn-small" onclick="editUser(<?php echo $user['id']; ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to delete this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="role" value="farmer">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                <?php elseif ($user['role'] === 'expert'): ?>
                                    <span class="status-approved">Approved Expert</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>
    </div>
</div>

<script src="../js/admin-dashboard.js"></script>
<?php include '../includes/footer.php'; ?>
