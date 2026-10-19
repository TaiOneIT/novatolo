<?php
$planTableMode = $planTableMode ?? 'about';
?>
<div class="plan-table-scroll">
    <table class="plan-table">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Plan Name</th>
                <th scope="col">Profit Rate</th>
                <th scope="col">Duration</th>
                <th scope="col">Investment Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($plans as $index => $plan):
                $planId = (int)$plan['id'];
                $planName = (string)$plan['name'];
                $planAmount = (float)$plan['min_amount'];
                $profitRate = (float)$plan['profit_rate'];
                $durationDays = (int)$plan['duration_days'];
                $planImage = $plan_images[$planId] ?? 'florian-media-dock-2898497.jpg';
                $planCategory = $planId <= 8 ? 'Phones' : ($planId <= 16 ? 'Watches' : 'Computers');
            ?>
                <tr>
                    <td data-label="ID"><span class="plan-id"><?= $planId ?></span></td>
                    <td data-label="Plan Name">
                        <div class="plan-table-name">
                            <img src="<?= e($planImage) ?>" alt="<?= e($planCategory) ?> plan image" loading="lazy">
                            <span class="plan-table-title">
                                <strong><?= e($planName) ?></strong>
                                <small><?= e($planCategory) ?></small>
                                <?php if ($planTableMode === 'public'): ?>
                                    <button class="plan-table-action" type="button" onclick="promptLogin()">Invest plan</button>
                                <?php elseif ($planTableMode === 'dashboard'):
                                    $isLimitReached = $index < 3 && ($userInvestmentsByPlan[$planId] ?? 0) >= 1;
                                    $totalReturn = $planAmount * (1 + $profitRate / 100);
                                ?>
                                    <?php if ($isLimitReached): ?>
                                        <span class="plan-table-limit">Plan already used</span>
                                    <?php else: ?>
                                        <button class="plan-table-action" type="button" onclick="openInvestModal(<?= $planId ?>, '<?= e(addslashes($planName)) ?>', <?= $planAmount ?>, <?= $profitRate ?>, <?= $durationDays ?>, <?= $totalReturn ?>)">Invest plan</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </td>
                    <td data-label="Profit Rate"><strong class="plan-table-rate"><?= number_format($profitRate, 2) ?>%</strong></td>
                    <td data-label="Duration"><?= $durationDays ?> days</td>
                    <td data-label="Investment Amount"><strong><?= money($planAmount) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>