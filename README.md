# Build the image
docker build -t localhost:5000/kannel-latest .

# Push to registry (if using local registry)
docker push localhost:5000/kannel-latest

# Deploy
docker-compose up -d