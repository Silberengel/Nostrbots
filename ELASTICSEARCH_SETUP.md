# Elasticsearch Integration for Nostrbots

This document describes the Elasticsearch integration that provides powerful logging, monitoring, and event search capabilities for the Nostrbots production environment.

## 🏗️ Architecture Overview

### Components

1. **Elasticsearch** - Search and analytics engine
2. **Kibana** - Web interface for data visualization
3. **Logstash** - Log processing and forwarding
4. **Event Indexer** - Custom service to index Nostr events
5. **Enhanced Backup** - Includes Elasticsearch snapshots

### Data Flow

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Containers    │───▶│    Logstash     │───▶│  Elasticsearch  │
│   (Logs)        │    │  (Processing)   │    │   (Storage)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                       │
┌─────────────────┐    ┌─────────────────┐            │
│   Orly Relay    │───▶│  Event Indexer  │────────────┘
│   (Events)      │    │  (Every 5min)   │
└─────────────────┘    └─────────────────┘
                                                       │
┌─────────────────┐    ┌─────────────────┐            │
│   Kibana        │◀───│   Dashboard     │◀───────────┘
│  (Visualization)│    │   (Analytics)   │
└─────────────────┘    └─────────────────┘
```

## 🚀 Quick Start

### 1. Setup with Elasticsearch

```bash
# Run the enhanced production setup
sudo ./setup-production-with-elasticsearch.sh
```

### 2. Access Services

- **Kibana Dashboard**: http://localhost:5601
- **Elasticsearch API**: http://localhost:9200
- **Jenkins**: http://localhost:8080

### 3. Verify Installation

```bash
# Check Elasticsearch health
nostrbots elasticsearch

# Check all services
nostrbots status

# View logs
nostrbots logs
```

## 📊 Data Indexing

### Log Data

All container logs are automatically processed and indexed:

- **Index Pattern**: `nostrbots-logs-*`
- **Time Field**: `@timestamp`
- **Retention**: 30 days (configurable)

**Log Types:**
- Container logs (Docker)
- Application logs (Nostrbots)
- Audit logs (Security events)
- System logs (OS level)

### Event Data

Nostr events from the relay are indexed every 5 minutes:

- **Index Pattern**: `nostr-events-*`
- **Time Field**: `created_at`
- **Retention**: 90 days (configurable)

**Event Fields:**
- `id` - Event ID
- `pubkey` - Author public key
- `created_at` - Event timestamp
- `kind` - Event kind
- `content` - Event content (searchable)
- `tags` - Event tags
- `sig` - Event signature
- `relay` - Source relay
- `indexed_at` - Indexing timestamp

## Search Capabilities

### Event Search Examples

```bash
# Search for events by author
curl -X GET "localhost:9200/nostr-events-*/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "term": {
      "pubkey": "npub1..."
    }
  }
}'

# Search for events by content
curl -X GET "localhost:9200/nostr-events-*/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "match": {
      "content": "bitcoin"
    }
  }
}'

# Search for events by kind
curl -X GET "localhost:9200/nostr-events-*/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "term": {
      "kind": 1
    }
  }
}'
```

### Log Search Examples

```bash
# Search for error logs
curl -X GET "localhost:9200/nostrbots-logs-*/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "term": {
      "level": "ERROR"
    }
  }
}'

# Search for specific container logs
curl -X GET "localhost:9200/nostrbots-logs-*/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "term": {
      "container_name": "nostrbots-orly-relay"
    }
  }
}'
```

## 📈 Monitoring & Alerting

### Key Metrics

1. **Event Volume** - Number of events indexed per hour/day
2. **Error Rate** - Percentage of failed operations
3. **Response Time** - API response times
4. **Disk Usage** - Elasticsearch data growth
5. **Memory Usage** - Container resource usage

### Kibana Dashboards

Pre-configured dashboards include:

- **System Overview** - Overall system health
- **Event Analytics** - Event patterns and trends
- **Error Monitoring** - Error logs and patterns
- **Security Events** - Security-related events
- **Performance Metrics** - System performance

### Alerting (Future Enhancement)

Potential alerting rules:

- High error rate (>5% errors)
- Low disk space (<10% free)
- High memory usage (>80%)
- Unusual event patterns
- Security events detected

## 🔧 Configuration

### Elasticsearch Settings

```yaml
# docker-compose.production-with-elasticsearch.yml
elasticsearch:
  environment:
    - "ES_JAVA_OPTS=-Xms1g -Xmx1g"  # Adjust based on available RAM
    - discovery.type=single-node
    - xpack.security.enabled=false
```

### Logstash Configuration

```ruby
# config/logstash/logstash.conf
input {
  docker {
    containers => ["nostrbots-orly-relay", "nostrbots-jenkins", ...]
  }
}

filter {
  # Custom parsing rules
}

output {
  elasticsearch {
    hosts => ["elasticsearch:9200"]
    index => "nostrbots-logs-%{+YYYY.MM.dd}"
  }
}
```

### Index Lifecycle Management

```bash
# Create lifecycle policy
curl -X PUT "localhost:9200/_ilm/policy/nostrbots-logs-policy" -H 'Content-Type: application/json' -d'
{
  "policy": {
    "phases": {
      "hot": {
        "actions": {
          "rollover": {
            "max_size": "10GB",
            "max_age": "7d"
          }
        }
      },
      "delete": {
        "min_age": "30d"
      }
    }
  }
}'
```

## 💾 Backup & Recovery

### Elasticsearch Snapshots

```bash
# Create snapshot repository
curl -X PUT "localhost:9200/_snapshot/backup_repo" -H 'Content-Type: application/json' -d'
{
  "type": "fs",
  "settings": {
    "location": "/backups/elasticsearch-snapshot"
  }
}'

# Create snapshot
curl -X PUT "localhost:9200/_snapshot/backup_repo/backup_$(date +%Y%m%d)" -H 'Content-Type: application/json' -d'
{
  "indices": "nostr-events*,nostrbots-logs*",
  "ignore_unavailable": true,
  "include_global_state": false
}'
```

### Restore from Snapshot

```bash
# Restore snapshot
curl -X POST "localhost:9200/_snapshot/backup_repo/backup_20240101/_restore" -H 'Content-Type: application/json' -d'
{
  "indices": "nostr-events*,nostrbots-logs*",
  "ignore_unavailable": true,
  "include_global_state": false
}'
```

## 🔒 Security Considerations

### Network Security

- All services bind to localhost only
- Internal Docker network for service communication
- No external access to Elasticsearch/Kibana

### Data Security

- No authentication enabled (single-node setup)
- Data encrypted at rest (filesystem level)
- Logs may contain sensitive information

### Production Recommendations

1. **Enable Authentication**
   ```yaml
   environment:
     - xpack.security.enabled=true
     - xpack.security.transport.ssl.enabled=true
   ```

2. **Use SSL/TLS**
   ```yaml
   environment:
     - xpack.security.transport.ssl.enabled=true
     - xpack.security.http.ssl.enabled=true
   ```

3. **Implement Access Control**
   - Use role-based access control (RBAC)
   - Limit Kibana access to authorized users
   - Monitor access logs

## 📊 Performance Tuning

### Memory Allocation

```yaml
# Recommended settings based on available RAM
environment:
  - "ES_JAVA_OPTS=-Xms2g -Xmx2g"  # 50% of available RAM
```

### Index Settings

```bash
# Optimize for time-series data
curl -X PUT "localhost:9200/nostr-events-*/_settings" -H 'Content-Type: application/json' -d'
{
  "index": {
    "number_of_shards": 1,
    "number_of_replicas": 0,
    "refresh_interval": "30s"
  }
}'
```

### Query Optimization

- Use filters instead of queries when possible
- Limit result size with `size` parameter
- Use date range filters for time-based queries
- Consider using aggregations for analytics

## 🚨 Troubleshooting

### Common Issues

1. **Elasticsearch won't start**
   ```bash
   # Check memory settings
   docker logs nostrbots-elasticsearch
   
   # Verify disk space
   df -h
   ```

2. **High memory usage**
   ```bash
   # Check JVM heap usage
   curl -X GET "localhost:9200/_nodes/stats/jvm"
   
   # Adjust memory settings
   # Edit docker-compose file and restart
   ```

3. **Slow queries**
   ```bash
   # Check slow query log
   curl -X GET "localhost:9200/_nodes/stats/indices/search"
   
   # Optimize index settings
   # Add more filters to queries
   ```

### Monitoring Commands

```bash
# Check cluster health
curl -X GET "localhost:9200/_cluster/health"

# Check index status
curl -X GET "localhost:9200/_cat/indices?v"

# Check node stats
curl -X GET "localhost:9200/_nodes/stats"

# Check disk usage
curl -X GET "localhost:9200/_cat/allocation?v"
```

## 🔄 Maintenance

### Regular Tasks

1. **Monitor disk usage** - Elasticsearch data grows over time
2. **Check index health** - Ensure indices are not in red state
3. **Review log retention** - Adjust retention policies as needed
4. **Update configurations** - Keep Elasticsearch and Kibana updated
5. **Backup verification** - Test restore procedures regularly

### Automated Maintenance

The system includes automated:

- Daily backups with Elasticsearch snapshots
- Log rotation and cleanup
- Index lifecycle management
- Health checks and monitoring

## 📚 Additional Resources

- [Elasticsearch Documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)
- [Kibana Documentation](https://www.elastic.co/guide/en/kibana/current/index.html)
- [Logstash Documentation](https://www.elastic.co/guide/en/logstash/current/index.html)
- [ELK Stack Best Practices](https://www.elastic.co/guide/en/elasticsearch/guide/current/index.html)
