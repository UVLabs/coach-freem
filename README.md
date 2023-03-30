## What it does.

Coach Freem receives your webhooks from Freemius, dusts them off, gives them a pep talk, and then sends them off to Mautic (he doesn't know that he's an app).

## Why. 

Because automation.

## How to use it.

Docs still loading...but you can probably figure it quickly.

Coach Freem was intended to be run as a Google Cloud Function that comes up every time the various webhooks are sent by Freemius. Google Cloud functions has a nice free tier so there's that.

Coach isn't limited to Google Cloud Functions, he can easily be moved to your very own server with a few tweaks. All you'd need to do is create a route on your server/website that accepts POST requests, and pass that request body to Coach. 
That pretty much happens on the first few lines of the `init` function. You can use a framework such as [SlimPHP](https://www.slimframework.com/) if you'd like to go that route.

Coach makes use of Mautic's Basic Auth, so all you need to do is create an Admin user on your Mautic install and change the details in `setClient` method, inside `Client.php` in the `includes` folder. You also need to enable the REST API on your Mautic install.

By default the method expects environment variables for these values, but you can set the details manually for testing or actually set them inside your $PATH.

In production you'd set the set environment variables inside the Google Functions config.

## What's missing.

Well...I put this together to *just* get some automation set up for Mautic to further automate my email marketing (the whole reason I'm using it). So pull requests are welcome.

Missing:

- Handle when a user opts out of marketing.

- Handle when a user deactivates (an opinion needs to be made for that, do we add them to a special segment, or do we tag them with a special tag?)

- Handle what happens when a user uninstalls the plugin/theme (an opinion needs to be made for that, do we add them to a special segment, or do we tag them with a special tag?)