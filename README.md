# Script for transfering iCanteen data to Google Calendar

**Made with ❤️ by Jakub Vorel**

First, you need Google Calendar API => [API Quickstart | Google Developers](https://developers.google.com/calendar/api/quickstart/php)

After creating new app in Google Developer Console you need to download credentials.json and put it in the root folder 🔧

Start the script and you will be automatically prompted to authenticate yourself. Paste the code into the terminal and token.json will be automatically created.

Now create new calendar in your Google Calendar and copy the new calendar's id into the code:

```php
$calendarId = $_ENV['CALENDAR_ID'];
```

You can run the script once more and now you will be able to select the menu for x days (depending on the kitchen 😉) 
