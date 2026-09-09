# RabbitMQ env.php snippet for AI_SeoContent (AMQP mode)
#
# Merge into app/etc/env.php under the top-level 'queue' key.
# Then set Admin → AI → SEO Content Generator → Queue Connection = RabbitMQ (AMQP)

'queue' => [
    'amqp' => [
        'host' => '127.0.0.1',
        'port' => '5672',
        'user' => 'guest',
        'password' => 'guest',
        'virtualhost' => '/',
    ],
    'consumers_wait_for_messages' => 1,
],

# Start consumer after setup:upgrade:
#   bin/magento ai:seo:consumer:start
#
# Supervisor (2 workers):
#   numprocs=2
#   command=php /var/www/magento/bin/magento ai:seo:consumer:start --max-messages=10000
