<table class="table table-striped">
    <thead>
        <tr>
            <th>Name</th>
            <th>Age</th>
            <th>Gender</th>
            <th>Phone</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($patients as $patient): ?>
        <tr>
            <td><?= htmlspecialchars("{$patient['firstname']} {$patient['lastname']}") ?></td>
            <td><?= htmlspecialchars($patient['age']) ?></td>
            <td><?= htmlspecialchars($patient['gender']) ?></td>
            <td><?= htmlspecialchars($patient['phone']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>