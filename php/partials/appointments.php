<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Patient Name</th>
            <th>Doctor Name</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($appointments as $appointment): ?>
        <tr>
            <td><?= htmlspecialchars($appointment['id']) ?></td>
            <td><?= htmlspecialchars($appointment['patient_name']) ?></td>
            <td><?= htmlspecialchars($appointment['doctor_name']) ?></td>
            <td><?= htmlspecialchars($appointment['appointment_date']) ?></td>
            <td>
                <?php if ($appointment['status'] === 'confirmed'): ?>
                    <span class="badge bg-success">Confirmed</span>
                <?php elseif ($appointment['status'] === 'cancelled'): ?>
                    <span class="badge bg-danger">Cancelled</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Pending</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($appointment['status'] === 'confirmed'): ?>
                    <button class="btn btn-danger update-status" data-id="<?= $appointment['id'] ?>" data-status="cancelled">
                        Cancel
                    </button>
                <?php elseif ($appointment['status'] === 'cancelled'): ?>
                    <button class="btn btn-success update-status" data-id="<?= $appointment['id'] ?>" data-status="confirmed">
                        Confirm
                    </button>
                <?php else: ?>
                    <button class="btn btn-danger update-status" data-id="<?= $appointment['id'] ?>" data-status="cancelled">
                        Cancel
                    </button>
                    <button class="btn btn-success update-status" data-id="<?= $appointment['id'] ?>" data-status="confirmed">
                        Confirm
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>