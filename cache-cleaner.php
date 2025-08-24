<?php
// Traitement des requêtes Ajax en premier
if (isset($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $dirs = ['cache', 'administrator/cache'];
    $response = ['success' => false, 'data' => null, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'scan':
                $response['data'] = scanCacheDirectories($dirs);
                $response['success'] = true;
                break;
                
            case 'clean':
                $response['data'] = cleanCacheDirectories($dirs);
                $response['success'] = true;
                break;
                
            default:
                $response['message'] = 'Action non reconnue';
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

function scanCacheDirectories($dirs) {
    $totalFiles = 0;
    $totalDirs = 0;
    $totalSize = 0;
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalFiles++;
                    $totalSize += $file->getSize();
                } elseif ($file->isDir()) {
                    $totalDirs++;
                }
                
                // Pause pour éviter les timeouts sur les gros volumes
                if (($totalFiles + $totalDirs) % 1000 === 0) {
                    usleep(1000); // 1ms de pause tous les 1000 éléments
                }
            }
        } catch (Exception $e) {
            // Ignorer les dossiers inaccessibles
            continue;
        }
    }
    
    return [
        'fileCount' => $totalFiles,
        'dirCount' => $totalDirs,
        'totalSize' => $totalSize
    ];
}

function cleanCacheDirectories($dirs) {
    $deletedFiles = 0;
    $deletedDirs = 0;
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                try {
                    if ($file->isFile()) {
                        if (unlink($file->getPathname())) {
                            $deletedFiles++;
                        }
                    } elseif ($file->isDir()) {
                        if (rmdir($file->getPathname())) {
                            $deletedDirs++;
                        }
                    }
                    
                    // Pause pour éviter la surcharge serveur
                    if (($deletedFiles + $deletedDirs) % 100 === 0) {
                        usleep(5000); // 5ms de pause tous les 100 suppressions
                    }
                    
                } catch (Exception $e) {
                    // Ignorer les erreurs de fichiers individuels
                    continue;
                }
            }
        } catch (Exception $e) {
            // Ignorer les dossiers inaccessibles
            continue;
        }
    }
    
    return [
        'deletedFiles' => $deletedFiles,
        'deletedDirs' => $deletedDirs
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyeur de Cache</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .scan-section {
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9ff;
            border: 2px solid #e1e8ff;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            display: none;
        }

        .info-card.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            padding: 10px 0;
            border-bottom: 1px solid #e1e8ff;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
        }

        .info-value {
            font-size: 1.1rem;
            color: #667eea;
            font-weight: bold;
        }

        .btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
            min-width: 150px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
        }

        .progress-container {
            margin: 20px 0;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e1e8ff;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .progress-text {
            margin-top: 10px;
            color: #666;
            font-weight: 500;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: none;
        }

        .alert.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hidden {
            display: none;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .modal p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .copyright {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .copyright p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }

        .copyright a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .copyright a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .copyright {
                position: relative;
                bottom: auto;
                left: auto;
                transform: none;
                margin-top: 30px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗑️ Nettoyeur de Cache</h1>
            <p>Gérez efficacement votre cache système</p>
        </div>

        <div class="scan-section">
            <button id="scanBtn" class="btn">🔍 Scanner le Cache</button>
        </div>

        <div id="infoCard" class="info-card">
            <h3>📊 Analyse du Cache</h3>
            <div class="info-item">
                <span class="info-label">Nombre de fichiers :</span>
                <span id="fileCount" class="info-value">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Nombre de dossiers :</span>
                <span id="dirCount" class="info-value">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Taille totale :</span>
                <span id="totalSize" class="info-value">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Dossiers analysés :</span>
                <span id="directories" class="info-value">cache/, administrator/cache/</span>
            </div>
        </div>

        <div id="actionButtons" class="hidden">
            <button id="cleanBtn" class="btn btn-danger">🧹 Nettoyer le Cache</button>
            <button id="rescanBtn" class="btn btn-secondary">🔄 Re-scanner</button>
        </div>

        <div id="progressContainer" class="progress-container">
            <div class="progress-bar">
                <div id="progressFill" class="progress-fill"></div>
            </div>
            <div id="progressText" class="progress-text">Préparation...</div>
        </div>

        <div id="alertContainer"></div>
    </div>

    <!-- Copyright -->
    <div class="copyright">
        <p>© 2025 - Développé par <a href="https://www.techno.rn.tn/" target="_blank">Techno RN</a></p>
    </div>

    <!-- Modal de confirmation -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ Confirmation de suppression</h3>
            <p id="confirmText">Êtes-vous sûr de vouloir supprimer tous les fichiers de cache ?</p>
            <button id="confirmYes" class="btn btn-danger">Oui, supprimer</button>
            <button id="confirmNo" class="btn btn-secondary">Annuler</button>
        </div>
    </div>

    <script>
        class CacheCleaner {
            constructor() {
                this.scanBtn = document.getElementById('scanBtn');
                this.cleanBtn = document.getElementById('cleanBtn');
                this.rescanBtn = document.getElementById('rescanBtn');
                this.infoCard = document.getElementById('infoCard');
                this.actionButtons = document.getElementById('actionButtons');
                this.progressContainer = document.getElementById('progressContainer');
                this.confirmModal = document.getElementById('confirmModal');
                this.alertContainer = document.getElementById('alertContainer');
                
                this.scanData = null;
                this.initEvents();
            }

            initEvents() {
                this.scanBtn.addEventListener('click', () => this.scanCache());
                this.cleanBtn.addEventListener('click', () => this.showConfirmation());
                this.rescanBtn.addEventListener('click', () => this.scanCache());
                document.getElementById('confirmYes').addEventListener('click', () => this.cleanCache());
                document.getElementById('confirmNo').addEventListener('click', () => this.hideConfirmation());
            }

            async scanCache() {
                this.setButtonLoading(this.scanBtn, true, '🔍 Scan en cours...');
                this.hideAlert();
                this.infoCard.classList.remove('show');
                this.actionButtons.classList.add('hidden');

                try {
                    const response = await this.makeRequest('scan');
                    this.scanData = response;
                    this.displayScanResults(response);
                } catch (error) {
                    console.error('Erreur scan:', error);
                    this.showAlert('Erreur lors du scan : ' + error.message, 'error');
                } finally {
                    this.setButtonLoading(this.scanBtn, false, '🔍 Scanner le Cache');
                }
            }

            displayScanResults(data) {
                document.getElementById('fileCount').textContent = data.fileCount.toLocaleString();
                document.getElementById('dirCount').textContent = data.dirCount.toLocaleString();
                document.getElementById('totalSize').textContent = this.formatBytes(data.totalSize);
                
                this.infoCard.classList.add('show');
                this.actionButtons.classList.remove('hidden');

                if (data.fileCount === 0 && data.dirCount === 0) {
                    this.showAlert('✨ Le cache est déjà vide !', 'success');
                    this.cleanBtn.disabled = true;
                } else {
                    this.cleanBtn.disabled = false;
                }
            }

            showConfirmation() {
                const text = `Vous êtes sur le point de supprimer :<br><br>
                    <strong>${this.scanData.fileCount.toLocaleString()}</strong> fichiers<br>
                    <strong>${this.scanData.dirCount.toLocaleString()}</strong> dossiers<br>
                    <strong>${this.formatBytes(this.scanData.totalSize)}</strong> au total<br><br>
                    Cette action est irréversible.`;
                
                document.getElementById('confirmText').innerHTML = text;
                this.confirmModal.style.display = 'block';
            }

            hideConfirmation() {
                this.confirmModal.style.display = 'none';
            }

            async cleanCache() {
                this.hideConfirmation();
                this.progressContainer.style.display = 'block';
                this.setButtonLoading(this.cleanBtn, true, '🧹 Nettoyage...');
                this.hideAlert();

                // Simulation de progression
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += Math.random() * 10;
                    if (progress > 95) progress = 95;
                    this.updateProgress(progress);
                }, 200);

                try {
                    const response = await this.makeRequest('clean');
                    clearInterval(progressInterval);
                    this.updateProgress(100);

                    setTimeout(() => {
                        this.showAlert(`✅ Cache nettoyé avec succès ! ${response.deletedFiles} fichiers et ${response.deletedDirs} dossiers supprimés.`, 'success');
                        
                        // Reset UI
                        this.scanData = null;
                        this.infoCard.classList.remove('show');
                        this.actionButtons.classList.add('hidden');
                        this.progressContainer.style.display = 'none';
                    }, 500);
                    
                } catch (error) {
                    clearInterval(progressInterval);
                    console.error('Erreur nettoyage:', error);
                    this.showAlert('❌ Erreur lors du nettoyage : ' + error.message, 'error');
                    this.progressContainer.style.display = 'none';
                } finally {
                    this.setButtonLoading(this.cleanBtn, false, '🧹 Nettoyer le Cache');
                }
            }

            async makeRequest(action) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    
                    xhr.open('POST', window.location.href, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.onreadystatechange = () => {
                        if (xhr.readyState === 4) {
                            console.log('Status:', xhr.status);
                            console.log('Response:', xhr.responseText);
                            
                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        resolve(response.data);
                                    } else {
                                        reject(new Error(response.message || 'Erreur inconnue'));
                                    }
                                } catch (e) {
                                    console.error('Erreur parsing JSON:', e);
                                    console.error('Réponse reçue:', xhr.responseText);
                                    reject(new Error('Réponse invalide du serveur'));
                                }
                            } else {
                                reject(new Error(`Erreur HTTP: ${xhr.status}`));
                            }
                        }
                    };

                    xhr.send(`action=${action}`);
                });
            }

            updateProgress(percent) {
                document.getElementById('progressFill').style.width = percent + '%';
                document.getElementById('progressText').textContent = `Progression: ${Math.round(percent)}%`;
            }

            setButtonLoading(button, isLoading, text) {
                button.disabled = isLoading;
                if (isLoading) {
                    button.innerHTML = text + '<div class="spinner"></div>';
                } else {
                    button.innerHTML = text;
                }
            }

            showAlert(message, type) {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type} show`;
                alert.innerHTML = message;
                
                this.alertContainer.innerHTML = '';
                this.alertContainer.appendChild(alert);
            }

            hideAlert() {
                this.alertContainer.innerHTML = '';
            }

            formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', () => {
            new CacheCleaner();
        });
    </script>
</body>
</html>
