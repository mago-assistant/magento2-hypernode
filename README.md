# Mago Hypernode

Hypernode hosting add-on for [Mago](https://github.com/mago-assistant/mago), the open source AI admin
assistant for Magento 2 and Mage-OS. Ask "is the server slow?" in the Mago chat and get live answers
from the Hypernode the store runs on.

## What it adds

One skill, `hypernode_status`, with seven actions:

| Action | Data | Source | Access |
|---|---|---|---|
| `server_overview` | Plan, PHP and MySQL version, Redis size; on the node also CPU usage, load, memory, swap, uptime and disk | Hypernode API, `/proc` | Read |
| `php_workers` | Busy and idle PHP-FPM workers, longest running requests | Hypernode API | Read |
| `http_traffic` | Request rate, status codes, 5xx rate and paths, Varnish/PHP handlers, PHP response times, top paths, countries and user agents over the last N minutes | `/var/log/nginx/access.log` on the node | Read |
| `node_settings` | Services and versions on the node: MailHog, Varnish, Redis/Valkey, OpenSearch, RabbitMQ, PHP/MySQL/Node.js, Xdebug and other profilers | Hypernode API | Read |
| `recent_flows` | Recent node tasks (settings changes, backups, updates) and their state | Hypernode API | Read |
| `list_annotations` | Custom [Hypernode Insights](https://insights.hypernode.com) annotations | Hypernode API | Read |
| `create_annotation` | Mark the current moment in the Insights graphs | Hypernode API | Write, asks first |

Example prompts:

```
Is the server slow right now?
How busy are the PHP workers?
Show the HTTP traffic of the last 30 minutes
How many 5xx errors did we serve in the past hour, and on which pages?
What is the Varnish hit rate at the moment?
Which plan are we on and how full is the disk?
Is MailHog enabled?
Which OpenSearch version do we run?
What changed on the node recently?
Mark "Price import finished" in Insights
```

Server metrics are a snapshot of this moment; Insights remains the place for history and graphs.

## Requirements

- [Mago](https://github.com/mago-assistant/mago) >= 2.0 or its `main` branch (`mago-assistant/mago`)
- Magento >= 2.4.9 or Mage-OS >= 3.0, PHP >= 8.2
- A Hypernode with its API token

## Installation

Install Mago first, following [its README](https://github.com/mago-assistant/mago#installation).
Neither package has a tagged release yet, so both come from their `main` branch, and this add-on is
installed straight from GitHub:

```bash
composer config repositories.mago-hypernode vcs https://github.com/mago-assistant/magento2-hypernode
composer require mago-assistant/mago:dev-main mago-assistant/magento2-hypernode:dev-main
bin/magento module:enable MagoAssistant_Hypernode
bin/magento setup:upgrade
```

## Configuration

`Stores > Configuration > Mago Assistant > Hypernode`

| Field | Config path | Default | Purpose |
|---|---|---|---|
| App name | `mago/hypernode/app_name` | detected from the hostname | The Hypernode app, e.g. `yourshop` for yourshop.hypernode.io |
| API token | `mago/hypernode/api_token` | read from `/etc/hypernode/hypernode_api_token` | Stored encrypted, marked sensitive |

On a Hypernode nothing needs filling in. Off the node (staging, local Docker) fill in both: the API
actions then work, while `server_overview`'s live block and `http_traffic` report that they are only
available on the node itself, because `/proc` and the access log would describe some other machine.

Using the skill requires the `Magento_Backend::system` ACL resource plus Mago's own skill permissions;
`create_annotation` goes through Mago's confirmation flow.

## Privacy and security

- The token is stored encrypted, hidden by `config:show` and `app:config:dump`, and never appears in
  tool output or error messages. Requests go straight from your store to api.hypernode.com.
- Client addresses, users and query strings never leave the server: `distinct_clients` is a count over
  hashed addresses, and admin `/key/<secret>/` segments and other long tokens in paths are masked.
- Every output field is classified for Mago's privacy filter.

## Development

Run from a Magento install that has the module as a path repository, or after `composer update` in the
module for a standalone checkout:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpstan analyse -c phpstan.neon.dist
vendor/bin/phpcs --standard=phpcs.xml --severity=9 .
```

## License

MIT
