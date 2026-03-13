# Crypto Exchange Queue System

## Overview

The crypto exchange backend implements a robust queue system for high-throughput order processing and price updates. The system uses Redis for production environments with multiple specialized queues for different types of operations.

## Queue Architecture

### Queue Types

1. **Trading Queue** (`trading`)
   - High-priority queue for order matching operations
   - Processes order placement and matching logic
   - Retry after: 30 seconds
   - Critical for trading performance

2. **Prices Queue** (`prices`)
   - Medium-priority queue for price updates
   - Handles cryptocurrency price simulation
   - Retry after: 60 seconds
   - Updates every 30 seconds

3. **Default Queue** (`default`)
   - General-purpose queue for other operations
   - Background tasks and maintenance operations
   - Standard retry configuration

### Job Classes

#### ProcessOrderMatching
- **Purpose**: Asynchronous order matching for cryptocurrencies
- **Queue**: `trading`
- **Timeout**: 30 seconds
- **Retries**: 3 attempts
- **Triggered**: When orders are placed or periodically

#### UpdateCryptocurrencyPrices
- **Purpose**: Update cryptocurrency prices with volatility simulation
- **Queue**: `prices`
- **Timeout**: 60 seconds
- **Retries**: 2 attempts
- **Triggered**: Every 30 seconds via scheduler

## Configuration

### Environment Variables

```env
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### Queue Configuration (config/queue.php)

```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
],

'trading' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'trading',
    'retry_after' => 30,
],

'prices' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'prices',
    'retry_after' => 60,
],
```

## Management Commands

### Queue Worker Management

```bash
# Start queue workers
php artisan queue:manage start --workers=3

# Stop all workers
php artisan queue:manage stop

# Restart workers
php artisan queue:manage restart --workers=5

# Check worker status
php artisan queue:manage status
```

### Order Processing

```bash
# Process orders for all cryptocurrencies (synchronous)
php artisan trading:process-matches --all

# Process orders for specific cryptocurrency
php artisan trading:process-matches BTC

# Queue order processing (asynchronous)
php artisan trading:process-matches --all --async
```

### Queue Monitoring

```bash
# Health check with full report
php artisan queue:health-check

# JSON output for monitoring systems
php artisan queue:health-check --json

# Alert-only mode
php artisan queue:health-check --alert
```

## Monitoring and Alerting

### Queue Health Monitoring

The system includes comprehensive queue monitoring:

- **Pending Jobs**: Number of jobs waiting to be processed
- **Processing Jobs**: Currently executing jobs
- **Failed Jobs**: Jobs that failed after all retries
- **Processing Rate**: Jobs processed per minute
- **Health Status**: healthy, warning, or critical

### Alert Thresholds

- **Warning**: Queue length > 100 jobs or processing rate < 1 job/minute
- **Critical**: Queue length > 1000 jobs
- **Pattern Detection**: 5+ failures in 5 minutes triggers critical alert

### Failure Handling

The system implements intelligent failure handling:

1. **Non-retryable Exceptions**: Validation errors, unauthorized access
2. **Database Failures**: Exponential backoff retry strategy
3. **Pattern Detection**: Automatic alerting for repeated failures
4. **Statistics Tracking**: Success/failure rates per queue

## Scheduled Tasks

### Automatic Scheduling (routes/console.php)

```php
// Continuous order matching
Schedule::command('trading:process-matches --all --async')
    ->everyMinute()
    ->withoutOverlapping();

// Price updates
Schedule::command('prices:update')
    ->everyMinute()
    ->withoutOverlapping();

// Queue monitoring
Schedule::command('queue:monitor trading,prices,default --max=1000')
    ->everyFiveMinutes();

// Failed job retry
Schedule::command('queue:retry all')
    ->hourly();
```

### Cron Configuration

Add to system crontab for Laravel scheduler:

```bash
* * * * * cd /path/to/crypto-exchange && php artisan schedule:run >> /dev/null 2>&1
```

## Production Deployment

### Redis Configuration

1. **Install Redis Server**
   ```bash
   # Ubuntu/Debian
   sudo apt-get install redis-server
   
   # CentOS/RHEL
   sudo yum install redis
   ```

2. **Configure Redis for Production**
   ```bash
   # Edit /etc/redis/redis.conf
   maxmemory 2gb
   maxmemory-policy allkeys-lru
   save 900 1
   save 300 10
   save 60 10000
   ```

3. **Start Redis Service**
   ```bash
   sudo systemctl start redis
   sudo systemctl enable redis
   ```

### Queue Worker Deployment

1. **Supervisor Configuration** (`/etc/supervisor/conf.d/crypto-exchange-workers.conf`)
   ```ini
   [program:crypto-exchange-trading]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/crypto-exchange/artisan queue:work redis --queue=trading --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   numprocs=3
   redirect_stderr=true
   stdout_logfile=/var/log/crypto-exchange-trading.log

   [program:crypto-exchange-prices]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/crypto-exchange/artisan queue:work redis --queue=prices --sleep=3 --tries=2 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/var/log/crypto-exchange-prices.log
   ```

2. **Start Supervisor**
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start crypto-exchange-trading:*
   sudo supervisorctl start crypto-exchange-prices:*
   ```

### Monitoring Integration

1. **Log Monitoring**: Configure log aggregation for queue logs
2. **Metrics Collection**: Export queue metrics to monitoring systems
3. **Alerting**: Set up alerts for critical queue failures
4. **Health Checks**: Include queue health in application health endpoints

## Performance Optimization

### Scaling Guidelines

- **Trading Queue**: 1 worker per 100 concurrent users
- **Prices Queue**: 1-2 workers sufficient for most loads
- **Memory Usage**: ~50MB per worker process
- **CPU Usage**: Minimal for queue processing

### Optimization Tips

1. **Batch Processing**: Group similar operations when possible
2. **Connection Pooling**: Use persistent Redis connections
3. **Memory Management**: Monitor worker memory usage
4. **Database Optimization**: Ensure proper indexing for queue queries

## Troubleshooting

### Common Issues

1. **Redis Connection Errors**
   ```bash
   # Check Redis status
   redis-cli ping
   
   # Check Laravel Redis connection
   php artisan tinker
   >>> Redis::ping()
   ```

2. **Queue Workers Not Processing**
   ```bash
   # Check worker processes
   ps aux | grep "queue:work"
   
   # Restart workers
   php artisan queue:restart
   ```

3. **High Queue Backlog**
   ```bash
   # Check queue length
   php artisan queue:monitor trading,prices,default
   
   # Scale workers
   php artisan queue:manage start --workers=10
   ```

### Debug Commands

```bash
# View failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry 1

# Clear all queues
php artisan queue:clear

# Monitor queue in real-time
watch -n 1 'php artisan queue:health-check --alert'
```

## Security Considerations

1. **Redis Security**: Configure Redis authentication and network restrictions
2. **Job Validation**: Validate all job parameters before processing
3. **Rate Limiting**: Implement rate limiting for job dispatch
4. **Audit Logging**: Log all queue operations for security auditing

## Testing

### Unit Tests

```bash
# Run queue-related tests
php artisan test --filter=Queue

# Test specific job classes
php artisan test tests/Unit/Jobs/ProcessOrderMatchingTest.php
```

### Load Testing

```bash
# Simulate high order volume
php artisan trading:load-test --orders=1000 --concurrent=50

# Monitor queue performance during load
php artisan queue:health-check --json | jq '.queue_details'
```

This queue system provides the foundation for high-performance, scalable cryptocurrency trading operations with comprehensive monitoring and failure handling capabilities.