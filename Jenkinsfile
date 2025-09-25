pipeline {
    agent {
        docker {
            image 'nostrbots:latest'
            args '-v /var/run/docker.sock:/var/run/docker.sock'
        }
    }
    
    environment {
        // Bot configuration
        BOT_CONFIG_DIR = '/app/bots'
        LOG_DIR = '/app/logs'
        
        // Nostr configuration (secrets should be in Jenkins credentials)
        NOSTR_BOT_KEY = credentials('nostr-bot-key')
        
        // Docker registry (if using private registry)
        DOCKER_REGISTRY = 'your-registry.com'
        DOCKER_IMAGE = 'nostrbots'
        DOCKER_TAG = "${env.BUILD_NUMBER}"
    }
    
    options {
        // Keep builds for 30 days
        buildDiscarder(logRotator(numToKeepStr: '30'))
        
        // Timeout after 30 minutes
        timeout(time: 30, unit: 'MINUTES')
        
        // Skip default checkout (we'll do it manually)
        skipDefaultCheckout()
    }
    
    stages {
        stage('Checkout') {
            steps {
                checkout scm
                script {
                    // Get git commit info for tagging
                    env.GIT_COMMIT_SHORT = sh(
                        script: 'git rev-parse --short HEAD',
                        returnStdout: true
                    ).trim()
                    env.GIT_BRANCH = sh(
                        script: 'git rev-parse --abbrev-ref HEAD',
                        returnStdout: true
                    ).trim()
                }
            }
        }
        
        stage('Build Docker Image') {
            steps {
                script {
                    // Build the Docker image
                    def image = docker.build("${DOCKER_IMAGE}:${DOCKER_TAG}")
                    
                    // Also tag as latest for local use
                    sh "docker tag ${DOCKER_IMAGE}:${DOCKER_TAG} ${DOCKER_IMAGE}:latest"
                    
                    // Store image reference for later stages
                    env.DOCKER_IMAGE_ID = image.id
                }
            }
        }
        
        stage('Test') {
            parallel {
                stage('Unit Tests') {
                    steps {
                        script {
                            docker.image("${DOCKER_IMAGE}:${DOCKER_TAG}").inside {
                                sh 'php nostrbots.php --help'
                                sh 'php run-tests.php'
                            }
                        }
                    }
                }
                
                stage('Bot Validation') {
                    steps {
                        script {
                            docker.image("${DOCKER_IMAGE}:${DOCKER_TAG}").inside {
                                sh 'docker-entrypoint.sh list-bots'
                                
                                // Validate bot configurations
                                sh '''
                                    echo "🔍 Validating bot configurations..."
                                    for bot_dir in /app/bots/*/; do
                                        if [ -d "$bot_dir" ]; then
                                            bot_name=$(basename "$bot_dir")
                                            config_file="$bot_dir/config.json"
                                            
                                            if [ -f "$config_file" ]; then
                                                echo "✅ Validating $bot_name..."
                                                # Validate JSON syntax
                                                jq empty "$config_file" || {
                                                    echo "❌ Invalid JSON in $config_file"
                                                    exit 1
                                                }
                                                
                                                # Check required fields
                                                name=$(jq -r '.name // empty' "$config_file")
                                                relays=$(jq -r '.relays // empty' "$config_file")
                                                
                                                if [ -z "$name" ]; then
                                                    echo "❌ Missing 'name' field in $config_file"
                                                    exit 1
                                                fi
                                                
                                                if [ -z "$relays" ]; then
                                                    echo "❌ Missing 'relays' field in $config_file"
                                                    exit 1
                                                fi
                                                
                                                echo "✅ $bot_name configuration valid"
                                            else
                                                echo "⚠️  No config.json found for $bot_name"
                                            fi
                                        fi
                                    done
                                '''
                            }
                        }
                    }
                }
            }
        }
        
        stage('Dry Run Tests') {
            steps {
                script {
                    docker.image("${DOCKER_IMAGE}:${DOCKER_TAG}").inside {
                        sh '''
                            echo "🧪 Running dry-run tests for all bots..."
                            
                            for bot_dir in /app/bots/*/; do
                                if [ -d "$bot_dir" ]; then
                                    bot_name=$(basename "$bot_dir")
                                    config_file="$bot_dir/config.json"
                                    
                                    if [ -f "$config_file" ]; then
                                        echo "🔍 Testing $bot_name (dry-run)..."
                                        
                                        # Test bot execution in dry-run mode
                                        docker-entrypoint.sh run-bot --bot "$bot_name" --dry-run --verbose || {
                                            echo "❌ Dry-run test failed for $bot_name"
                                            exit 1
                                        }
                                        
                                        echo "✅ $bot_name dry-run test passed"
                                    fi
                                fi
                            done
                        '''
                    }
                }
            }
        }
        
        stage('Deploy') {
            when {
                anyOf {
                    branch 'main'
                    branch 'master'
                    changeRequest()
                }
            }
            steps {
                script {
                    // Push to registry if configured
                    if (env.DOCKER_REGISTRY && env.DOCKER_REGISTRY != 'your-registry.com') {
                        docker.withRegistry("https://${DOCKER_REGISTRY}", 'docker-registry-credentials') {
                            docker.image("${DOCKER_IMAGE}:${DOCKER_TAG}").push()
                            docker.image("${DOCKER_IMAGE}:latest").push()
                        }
                    }
                    
                    // Deploy to staging/production if needed
                    if (env.BRANCH_NAME == 'main' || env.BRANCH_NAME == 'master') {
                        echo "🚀 Deploying to production..."
                        // Add deployment steps here
                    }
                }
            }
        }
    }
    
    post {
        always {
            // Clean up Docker images to save space
            sh '''
                docker rmi ${DOCKER_IMAGE}:${DOCKER_TAG} || true
                docker system prune -f || true
            '''
        }
        
        success {
            echo "✅ Pipeline completed successfully!"
            
            // Send success notification (customize as needed)
            script {
                if (env.BRANCH_NAME == 'main' || env.BRANCH_NAME == 'master') {
                    // Notify on main branch success
                    echo "🎉 Production deployment successful!"
                }
            }
        }
        
        failure {
            echo "❌ Pipeline failed!"
            
            // Send failure notification (customize as needed)
            script {
                // Add notification logic here (Slack, email, etc.)
                echo "🚨 Build failed - check logs for details"
            }
        }
        
        unstable {
            echo "⚠️  Pipeline completed with warnings"
        }
    }
}

// Scheduled builds for bot execution
pipeline {
    agent {
        docker {
            image 'nostrbots:latest'
        }
    }
    
    environment {
        NOSTR_BOT_KEY = credentials('nostr-bot-key')
    }
    
    triggers {
        // Run every hour to check for scheduled bots
        cron('0 * * * *')
    }
    
    stages {
        stage('Scheduled Bot Execution') {
            steps {
                script {
                    docker.image('nostrbots:latest').inside {
                        sh '''
                            echo "⏰ Checking for scheduled bots..."
                            current_time=$(date -u +%H:%M)
                            echo "Current UTC time: $current_time"
                            
                            # Run bots in schedule mode
                            docker-entrypoint.sh schedule
                        '''
                    }
                }
            }
        }
    }
    
    post {
        always {
            // Log the execution
            script {
                def timestamp = new Date().format('yyyy-MM-dd HH:mm:ss')
                echo "Scheduled execution completed at $timestamp"
            }
        }
    }
}
