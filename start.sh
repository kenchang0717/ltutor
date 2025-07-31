#!/bin/bash

echo "Starting cron..."
/usr/sbin/cron

echo "Starting Apache..."
exec apache2-foreground
