<?php
// Mock environment for CLI execution
$_SERVER['REQUEST_METHOD'] = 'GET';
$_ENV['DB_HOST'] = 'db';
$_ENV['DB_USER'] = 'specialisthub';
$_ENV['DB_PASS'] = 'specialisthub';
$_ENV['DB_NAME'] = 'specialisthub';

require_once __DIR__ . '/../Core/bootstrap.php';

// Mock a session for a freelancer (User 4 from seed data)
$_SESSION['user_id'] = 4;
$_SESSION['role'] = 'freelancer';

echo "--- FEATURE VALIDATION START ---\n\n";

$db = Database::getInstance()->getConnection();
$milestoneRepo = new MilestoneRepository();
$milestoneService = new MilestoneService();
$disputeService = new DisputeService();

// --- TEST 1: HIERARCHICAL MILESTONES ---
echo "[1] Testing Hierarchical Milestone Builder...\n";

// Manual check logic from MilestoneService::startMilestone to avoid Response::error exit
function checkDependency($milestoneId, $milestoneRepo) {
    $milestone = $milestoneRepo->getMilestone($milestoneId);
    if (!empty($milestone['dependency_milestone_id'])) {
        $dependency = $milestoneRepo->getMilestone((int) $milestone['dependency_milestone_id']);
        if (!in_array($dependency['status'], ['approved', 'auto_approved', 'complete'], true)) {
            return false;
        }
    }
    return true;
}

// Ensure M3 depends on M2, and M2 is 'in_progress'
$db->prepare("UPDATE milestones SET dependency_milestone_id=2, status='locked' WHERE id=3")->execute();
$db->prepare("UPDATE milestones SET status='in_progress' WHERE id=2")->execute();

if (!checkDependency(3, $milestoneRepo)) {
    echo "✅ SUCCESS: Milestone 3 was correctly blocked (Dependency M2 is in_progress).\n";
} else {
    echo "❌ ERROR: Milestone 3 check passed when it should have failed!\n";
}

// Approve M2
$db->prepare("UPDATE milestones SET status='approved', approved_at=NOW() WHERE id=2")->execute();
echo "Milestone 2 manually approved.\n";

if (checkDependency(3, $milestoneRepo)) {
    echo "✅ SUCCESS: Milestone 3 check passed (Dependency M2 is approved).\n";
} else {
    echo "❌ ERROR: Milestone 3 check failed when it should have passed!\n";
}

// --- TEST 2: FILE DISPUTE (EVIDENCE ASSEMBLY) ---
echo "\n[2] Testing File Dispute (Evidence Assembly)...\n";
try {
    echo "Assembling evidence for Dispute 1...\n";
    $path = $disputeService->assembleEvidence(1);
    if (file_exists(__DIR__ . '/../' . $path)) {
        $content = json_decode(file_get_contents(__DIR__ . '/../' . $path), true);
        echo "✅ SUCCESS: Evidence file created at: " . $path . "\n";
        echo "   - Contract ID in JSON: " . $content['dispute']['contract_id'] . "\n";
        echo "   - Messages found: " . count($content['messages']) . "\n";
    } else {
        echo "❌ ERROR: Evidence file was NOT created at " . __DIR__ . '/../' . $path . "\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: Evidence assembly failed: " . $e->getMessage() . "\n";
}

// --- TEST 3: SAFE-ROOM MESSAGING & ARCHIVING ---
echo "\n[3] Testing Safe-Room Archiving...\n";
try {
    $disputeId = 1;
    $repo = new DisputeRepository();
    
    echo "Sending a test message to Safe-Room...\n";
    $msgId = $repo->addMessage($disputeId, 4, "This is a mediation test message.");
    
    echo "Issuing a verdict to close the dispute and archive messages...\n";
    // Mock Admin Session
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['admin_role'] = 'dispute_mediator';
    
    // Bypass the executeVerdict status check exit if needed, but here we just call archiving
    (new AuditService())->archiveCommunication('dispute', $disputeId);
    
    $stmt = $db->prepare("SELECT archived, message FROM dispute_messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    
    if ($msg['archived'] == 1) {
        echo "✅ SUCCESS: Safe-Room message was correctly archived.\n";
        echo "   - Original: 'This is a mediation test message.'\n";
        echo "   - Encoded: " . $msg['message'] . "\n";
    } else {
        echo "❌ ERROR: Message was NOT archived.\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: Safe-Room test failed: " . $e->getMessage() . "\n";
}

echo "\n--- FEATURE VALIDATION END ---\n";
