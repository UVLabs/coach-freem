# What it does 🤔

Coach Freem receives your webhooks from Freemius, dusts them off, gives them a pep talk, and then sends them off to Mautic.

In other words, Coach Freem receives webhooks from Freemius, saves them, processes them, and creates/updates the respect Mautic contact based on the Webhook received by Freemius.

When a webhook is received from Freemius, the contents are saved to the `webhooks` folder. This is so that the connection from Freemius can be disconnected so as to not timeout at 10 seconds, which is the maximum amount of time Freemius allows it's webhooks to run.

A separate cron job then comes in to process the saved webhooks and creates/updates the mautic contact.

# Why 🤷

Because automation.

# How to use it 🛠️

Coach makes use of Mautic's Basic Auth, so all you need to do is:

- Create a custom field inside your Mautic install called `freemius_id`, make sure that the **Is Unique Identifier** switch for the field is set to **Yes**. Also make sure the alias is `freemius_id`

- Enable the Mautic RestAPI in its configuration settings

- Create an Admin *user* in Mautic

- Edit the `.env.example` file in the `coach-freem` folder then replace the placeholders with the user's credentials and the Mautic API URL (typically something like `https://<mautic-install-domain>/api/`

- Rename `.env.example` to `.env`

## Functions to edit before going live

The following functions inside `index.php` should be edited to match your plugin and mautic details:

- productIDs()
- customContactDataMappings()
- contactSegments()
- contactTags()
- excludedTLDs()
- isExcludedTLD()

Comments have been added to help explain how these functions should be edited. If comments can be made better then feel free to submit a pull request! 

## Freemius dashboard setup

Once you've dropped Coach Freem to your server. You should add the following URL to your Freemius Custom Webhooks area: `https://example.com/coach-freem/?save=1`. Replace `example.com` with your domain name. 

The example URL assumes you have the `coach-freem` folder placed in the root directory of your domain.

Copy this URL and head to `Freemius Dashboard->Integrations->Custom Webhooks`, create a new webhook with the URL you copied before but for the event types **ONLY SELECT THE CURRENT EVENTS THAT ARE BEING CONSUMED BY COACH FREEM**.

At the time of writing this line the only events being used by Coach Freem are:

- install.installed
- license.activated
- license.deactivated
- license.expired
- install.activated
- install.deactivated
- install.uninstalled

You can confirm this by checking the `process_webhook()` function in `index.php`. 

## Cron Job Setup

Add a cronjob that runs every 5 minutes to your server, the cronjob should hit the URL: `https://example.com/coach-freem/?process=1`. Replace `example.com` with your domain name. 

The example URL assumes you have the `coach-freem` folder placed in the root directory of your domain.

# Local Testing

You can start a local PHP server for testing your changes by running `php -S localhost:8080` in the root directory of Coach Freem. You will then be able to access Coach locally by going to `http://localhost:8080`. You can then `POST` Freemius webhooks to this URL using a tool like [Insomnia](https://insomnia.rest/) or [Postman](https://www.postman.com/).

An example Freemius request webhook can be found in `docs/sample-freemius-webhook.json`, simply copy the contents of this file and paste it as a JSON POST request inside Insomnia/Postman to `http://localhost:8080`.

You can change the `type` key in the JSON request to the webhook you want to test. 

For your own plugin/theme, you can use the Freemius webhook area to send all webhooks to a URL provided by https://webhook.site/ to examine the webhook content testing.


# What's missing 👨🏾‍💻

Well...I put this together to *just* get some automation set up for Mautic to further automate my email marketing (the whole reason I'm using it).

Missing:

- Handle when a user opts out of marketing.