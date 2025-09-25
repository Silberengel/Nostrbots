# Simple Docker Setup for Nostrbots

## 🚀 One Command Setup

```bash
# Build and run everything
docker build -t nostrbots . && docker run --rm -p 3334:3334 nostrbots
```

That's it! This will:
- ✅ Build next-orly v0.8.4 from source
- ✅ Generate a Nostr key automatically
- ✅ Start the next-orly relay on port 3334
- ✅ Configure the hello world bot
- ✅ Run the hello world bot and publish content
- ✅ Show you it's working

## 🔍 What You Get

- **next-orly relay**: http://localhost:3334
- **Published content**: The hello world bot publishes to the relay
- **Working setup**: Everything configured and tested

## 🎯 Usage

```bash
# Build the image
docker build -t nostrbots .

# Run it
docker run --rm -p 3334:3334 nostrbots

# Or run in background
docker run -d --name nostrbots -p 3334:3334 nostrbots

# Check logs
docker logs nostrbots

# Stop it
docker stop nostrbots
```

## 🔧 Custom Key

If you want to use your own Nostr key:

```bash
# Generate your key first
bash scripts/01-generate-key.sh

# Run with your key
docker run --rm -p 3334:3334 \
  -e NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED \
  nostrbots
```

## 📋 What's Included

- Ubuntu 22.04 base
- Java 11 (for Jenkins if needed later)
- PHP 8.1 with all extensions
- Go 1.22.5 (for building next-orly)
- next-orly v0.8.4 (built from source)
- Complete Nostrbots environment
- Hello world bot that runs automatically

## 🎉 Success!

When it works, you'll see:
```
✅ next-orly is running on port 3334
✅ Dry run test passed!
✅ Hello world bot published successfully!
📄 Content published to: bots/hello-world/output/
🌐 Relay available at: http://localhost:3334
🎉 Everything is working!
```

That's it! No more complexity. Just build and run.
