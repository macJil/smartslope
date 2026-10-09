# Test SmartSlope on a Phone

This is a historical LAN-testing guide. For the current MAMP FastCGI crash,
use [the local launch guide](docs/mamp-fastcgi-crash.md) on your Mac first.
The current launcher binds only to 127.0.0.1 and requires router.php; the old
plain php -S command below does not protect internal files and should not be
used with the current repository. Database settings now live in config.php.

## 1. Start the app on your Mac

1. Connect your Mac to the Wi-Fi network you will use on your phone.
2. Make sure MySQL is running and the project `.env` has the working database settings.
3. Open Terminal in the SmartSlope project folder:

   ```sh
   cd /Users/mac/Herd/smartslope
   ```

4. Start PHP's local server. If it is already running in another terminal, leave it running and skip this step.

   ```sh
   php -S 0.0.0.0:8000
   ```

   Keep this terminal open while testing.

## 2. Open the site on your phone

1. Find the Mac's current Wi-Fi IP address:

   ```sh
   ipconfig getifaddr en0
   ```

2. Connect the phone to the same non-guest Wi-Fi as the Mac. Turn off any VPN while testing.
3. In the phone browser, open `http://<MAC-IP>:8000/`. For example, if the Mac IP is `192.168.1.9`, open:

   ```text
   http://192.168.1.9:8000/
   ```

Use `http://`, not `https://`. The Herd address `smartslope.test` normally works only on the Mac, not on the phone.

## 3. Test the main flows

1. Register a resident account or sign in with an existing account.
2. On the dashboard, tap a map point. Confirm its popup shows the address and risk status, including `UNAVAILABLE` when no current reading exists.
3. Choose **View readings**. Refresh weather and confirm the reading appears. Weather refresh requires internet access.
4. Open **Submit Report**, select a point on the map, enter the required house or landmark and report details, then submit.
5. Sign in as an administrator on the phone. Open **All Reports**, choose **View**, and confirm the modal map marks the submitted point and shows its risk status.
6. Review or resolve the report if you want to test the admin workflow.

## 4. Stop the test server

Return to the Mac terminal running PHP and press `Ctrl+C`.

## If the phone cannot connect

- Confirm the phone and Mac are on the same Wi-Fi, not a guest network.
- Recheck the Mac IP; it can change when reconnecting to Wi-Fi.
- Keep the PHP server terminal open and make sure the URL includes port `8000`.
- If both devices are on the same network but the page still times out, check the router for **AP isolation** or **client isolation** settings.

The built-in PHP server is for local testing on a trusted network. Stop it when finished.
