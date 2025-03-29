<table class="table table-striped">
    <thead>
        <tr>
            <th>Doctor</th>
            <th>Available Day</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($schedules as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['doctor_name']) ?></td>
            <td><?= htmlspecialchars($s['available_day']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>