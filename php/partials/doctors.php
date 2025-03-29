<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Specialization</th>
            <th>Phone</th>
            <th>Account Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($doctors as $doctor): ?>
        <tr>
            <td><?= htmlspecialchars($doctor['id']) ?></td>
            <td><?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?></td>
            <td><?= htmlspecialchars($doctor['email']) ?></td>
            <td><?= htmlspecialchars($doctor['specialization'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?></td>
            <td>
                <a href="#" class="toggle-status" data-id="<?= $doctor['id'] ?>" data-status="<?= $doctor['account_status'] ?>">
                    <?php if ($doctor['account_status'] === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Inactive</span>
                    <?php endif; ?>
                </a>
            </td>
            <td>
                <button class="btn btn-danger delete-doctor" data-id="<?= $doctor['id'] ?>">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>