<?php
$dir = __DIR__ . '/../storage/app/demos/uploaded/2025/09/29/lock_child';
$lock = __DIR__ . '/../storage/app/demos/.mkdir_create_lock';
for ($i=0;$i<6;$i++) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        echo "fork failed\n"; exit(1);
    } elseif ($pid == 0) {
        usleep(rand(0,100000));
        $fp = fopen($lock, 'c');
        if ($fp === false) {
            echo "child " . getmypid() . " cannot open lock\n";
            exit(1);
        }
        if (!flock($fp, LOCK_EX)) {
            echo "child " . getmypid() . " cannot flock\n";
            fclose($fp);
            exit(1);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            echo "child " . getmypid() . " created\n";
        } else {
            echo "child " . getmypid() . " existed\n";
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        exit(0);
    }
}
while (pcntl_waitpid(0, $status) != -1) { }
echo "done\n";
