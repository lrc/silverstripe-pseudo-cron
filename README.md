Silverstripe Pseudo Cron
========================

Implements a cron like system triggered by page requests. Jobs are executed asynchronously so page load times aren't affected.

Usage
-----

Add a job to the CronTab. This should only be done once. Maybe in a `requireDefaultRecords()` function.

```php
CronTab::add(array(
	'Name' => 'Garbage collector',
	'Callback' => array('GarbageCollector','collect'),
	'Increment' => 600, // 10 mins
	'Description' => 'Take out the trash.',
	'Notify' => 'web@example.com' // Notifies only when something goes wrong.
));
```

Give the CronTab something to call:

```php
class GarbageCollector extends Controller {
	public static function collect() {
		// Take out the trash.
	}
}
```

Requirements
------------

* Silverstripe 3.0.3+

A 2.4.x compatible version is available in the 2.4 branch.