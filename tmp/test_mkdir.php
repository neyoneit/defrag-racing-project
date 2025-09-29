<?php
$dir = __DIR__ . '/storage/app/demos/uploaded/2025/09/29/aa';
for ($i=0;$i<3;$i++) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        echo "fork failed\n";
        exit(1);
    } elseif ($pid) {
        // parent
    } else {
        // child
        usleep(rand(0,50000));
        if (mkdir($dir, 0775, true) || is_dir($dir)) {
            echo "child created or existed: " . getmypid() . "\n";
        } else {
            echo "child failed: " . getmypid() . "\n";
        }
        exit(0);
    }
}
while (pcntl_waitpid(0, $status) != -1) {
    // wait for children
}
echo "done\n";
