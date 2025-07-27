<?php
// routes/web.php

use App\Controllers\DashboardController;
use App\Controllers\JobController;
use App\Controllers\AnalysisController;
use App\Controllers\ReportController;

// Dashboard Routes
$app->get('/dashboard', function ($request, $response, $args) use ($container) {
    $controller = new DashboardController($container->get('view'));
    return $controller->index($request, $response);
});

$app->get('/dashboard/stats', function ($request, $response, $args) use ($container) {
    $controller = new DashboardController($container->get('view'));
    return $controller->stats($request, $response);
});

$app->get('/dashboard/export', function ($request, $response, $args) use ($container) {
    $controller = new DashboardController($container->get('view'));
    return $controller->export($request, $response);
});

// Job Management Routes
$app->group('/jobs', function ($group) use ($container) {
    
    // List jobs (redirect to dashboard)
    $group->get('', function ($request, $response, $args) {
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    });
    
    // Create job form
    $group->get('/create', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->create($request, $response);
    });
    
    // Store new job
    $group->post('/create', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->store($request, $response);
    });
    
    // View specific job
    $group->get('/{id:[0-9]+}', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->show($request, $response, $args);
    });
    
    // Edit job form
    $group->get('/{id:[0-9]+}/edit', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->edit($request, $response, $args);
    });
    
    // Update job
    $group->post('/{id:[0-9]+}/edit', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->update($request, $response, $args);
    });
    
    // Delete job
    $group->post('/{id:[0-9]+}/delete', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->destroy($request, $response, $args);
    });
    
    // Trigger analysis
    $group->post('/{id:[0-9]+}/analyze', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->analyze($request, $response, $args);
    });
    
    // Download files
    $group->get('/{id:[0-9]+}/download/{type}', function ($request, $response, $args) use ($container) {
        $controller = new JobController(
            $container->get('view'),
            $container->get('jobAnalyzer'),
            $container->get('pdfParser')
        );
        return $controller->downloadFile($request, $response, $args);
    });
});

// Analysis Routes
$app->group('/analysis', function ($group) use ($container) {
    
    // View analysis details
    $group->get('/{id:[0-9]+}', function ($request, $response, $args) use ($container) {
        $controller = new AnalysisController($container->get('view'));
        return $controller->show($request, $response, $args);
    });
    
    // Re-run analysis
    $group->post('/{id:[0-9]+}/rerun', function ($request, $response, $args) use ($container) {
        $controller = new AnalysisController($container->get('view'));
        return $controller->rerun($request, $response, $args);
    });
    
    // Compare analyses
    $group->get('/compare/{id1:[0-9]+}/{id2:[0-9]+}', function ($request, $response, $args) use ($container) {
        $controller = new AnalysisController($container->get('view'));
        return $controller->compare($request, $response, $args);
    });
    
    // Analysis insights API
    $group->get('/{id:[0-9]+}/insights', function ($request, $response, $args) use ($container) {
        $controller = new AnalysisController($container->get('view'));
        return $controller->insights($request, $response, $args);
    });
});

// Report Routes
$app->group('/reports', function ($group) use ($container) {
    
    // Generate PDF report
    $group->get('/{id:[0-9]+}/pdf', function ($request, $response, $args) use ($container) {
        $controller = new ReportController($container->get('reportGenerator'));
        return $controller->generatePDF($request, $response, $args);
    });
    
    // Generate detailed analysis report
    $group->get('/{id:[0-9]+}/detailed', function ($request, $response, $args) use ($container) {
        $controller = new ReportController($container->get('reportGenerator'));
        return $controller->generateDetailed($request, $response, $args);
    });
    
    // Export analysis data
    $group->get('/{id:[0-9]+}/export/{format}', function ($request, $response, $args) use ($container) {
        $controller = new ReportController($container->get('reportGenerator'));
        return $controller->exportData($request, $response, $args);
    });
    
    // Bulk report generation
    $group->post('/bulk', function ($request, $response, $args) use ($container) {
        $controller = new ReportController($container->get('reportGenerator'));
        return $controller->generateBulk($request, $response, $args);
    });
});

// Utility Routes
$app->group('/utils', function ($group) use ($container) {
    
    // Health check
    $group->get('/health', function ($request, $response, $args) {
        $data = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'database' => 'connected',
            'ai_service' => $_ENV['AI_SERVICE_ENABLED'] === 'true' ? 'enabled' : 'disabled'
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Check AI service status
    $group->get('/ai-status', function ($request, $response, $args) use ($container) {
        $aiService = $container->get('aiAnalysis');
        
        try {
            $status = $aiService->checkStatus();
            $response->getBody()->write(json_encode($status));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
    
    // Start AI service
    $group->post('/ai-start', function ($request, $response, $args) use ($container) {
        $aiService = $container->get('aiAnalysis');
        
        try {
            $result = $aiService->startService();
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
    
    // Test file upload
    $group->post('/test-upload', function ($request, $response, $args) {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['file'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No file uploaded'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $uploadedFile = $uploadedFiles['file'];
        
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Upload error'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'file_info' => [
                'name' => $uploadedFile->getClientFilename(),
                'size' => $uploadedFile->getSize(),
                'type' => $uploadedFile->getClientMediaType()
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // System info
    $group->get('/system', function ($request, $response, $args) {
        $data = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'extensions' => [
                'gd' => extension_loaded('gd'),
                'pdo' => extension_loaded('pdo'),
                'zip' => extension_loaded('zip'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json')
            ],
            'disk_space' => [
                'free' => disk_free_space(__DIR__),
                'total' => disk_total_space(__DIR__)
            ]
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });
});

// Static file serving for development (remove in production)
if ($_ENV['APP_ENV'] === 'development') {
    $app->get('/assets/{file:.+}', function ($request, $response, $args) {
        $file = $args['file'];
        $filePath = __DIR__ . '/../public/assets/' . $file;
        
        if (!file_exists($filePath)) {
            return $response->withStatus(404);
        }
        
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
        
        