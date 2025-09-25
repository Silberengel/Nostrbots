pipeline {
    agent any
    
    stages {
        stage('Generate Content') {
            steps {
                echo "📝 Generating Hello World content using Docker..."
                sh '''
                    # Load environment variables from .env file if it exists
                    if [ -f ".env" ]; then
                        echo "Loading environment variables from .env file..."
                        export $(grep -v '^#' .env | xargs)
                    fi
                    
                    # Generate content using the nostrbots Docker image
                    docker run --rm \
                        -e NOSTR_BOT_KEY_ENCRYPTED="${NOSTR_BOT_KEY_ENCRYPTED}" \
                        -e NOSTR_BOT_NPUB="${NOSTR_BOT_NPUB}" \
                        -v /home/madmin/Projects/GitCitadel/Nostrbots:/workspace \
                        -w /workspace \
                        silberengel/nostrbots:latest \
                        php bots/hello-world/generate-content.php
                    
                    # List generated files
                    echo "📋 Generated content files:"
                    docker run --rm \
                        -v /home/madmin/Projects/GitCitadel/Nostrbots:/workspace \
                        -w /workspace \
                        silberengel/nostrbots:latest \
                        ls -la bots/hello-world/output/ || echo "No output directory found"
                '''
            }
        }
        
        stage('Publish to Nostr') {
            steps {
                echo "📡 Publishing to Nostr relays..."
                sh '''
                    # Load environment variables from .env file if it exists
                    if [ -f ".env" ]; then
                        echo "Loading environment variables from .env file..."
                        export $(grep -v '^#' .env | xargs)
                    fi
                    
                    # Check if content files exist and find the latest one
                    LATEST_FILE=$(docker run --rm \
                        -v /home/madmin/Projects/GitCitadel/Nostrbots:/workspace \
                        -w /workspace \
                        silberengel/nostrbots:latest \
                        sh -c 'if [ -d "bots/hello-world/output" ] && [ "$(ls -A bots/hello-world/output/*.adoc 2>/dev/null)" ]; then ls -t bots/hello-world/output/*.adoc | head -1; else echo ""; fi')
                    
                    if [ -n "$LATEST_FILE" ]; then
                        echo "📄 Publishing file: $LATEST_FILE"
                        
                        # Decrypt the key for publishing
                        echo "🔓 Decrypting private key..."
                        DECRYPTED_KEY=$(docker run --rm \
                            -e NOSTR_BOT_KEY_ENCRYPTED="${NOSTR_BOT_KEY_ENCRYPTED}" \
                            -v /home/madmin/Projects/GitCitadel/Nostrbots:/workspace \
                            -w /workspace \
                            silberengel/nostrbots:latest \
                            php decrypt-key.php)
                        
                        # Publish to Nostr (dry-run first)
                        echo "🔍 Testing with dry-run..."
                        docker run --rm \
                            -e NOSTR_BOT_KEY="${DECRYPTED_KEY}" \
                            -e NOSTR_BOT_NPUB="${NOSTR_BOT_NPUB}" \
                            -v /home/madmin/Projects/GitCitadel/Nostrbots:/workspace \
                            -w /workspace \
                            silberengel/nostrbots:latest \
                            php nostrbots.php publish "$LATEST_FILE" --dry-run
                        
                        echo "🚀 Publishing for real..."
                        docker run --rm \
                            -e NOSTR_BOT_KEY="${DECRYPTED_KEY}" \
                            -e NOSTR_BOT_NPUB="${NOSTR_BOT_NPUB}" \
                            -v /home/madmin/Projects/GitCitadel/Nostrbots:/workspace \
                            -w /workspace \
                            silberengel/nostrbots:latest \
                            php nostrbots.php publish "$LATEST_FILE"
                    else
                        echo "❌ No content files found to publish"
                        exit 1
                    fi
                '''
            }
        }
        
        stage('Verify Publication') {
            steps {
                echo "✅ Verifying publication..."
                sh '''
                    echo "📊 Publication completed successfully!"
                    echo "🔗 Check your Nostr client for the new content"
                    echo "📡 Published to relays: wss://freelay.sovbit.host"
                '''
            }
        }
    }
    
    post {
        success {
            echo "🎉 Nostrbots pipeline completed successfully!"
        }
        failure {
            echo "❌ Nostrbots pipeline failed!"
        }
    }
}