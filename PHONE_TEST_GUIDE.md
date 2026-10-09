# Test SmartSlope on a Phone

Use MAMP/XAMPP Apache and MySQL with config.php database settings. See
[localhost setup](docs/mamp-localhost.md). The website needs no shell launcher.

## 1. Start the app on your Mac

1. Connect your Mac to the Wi-Fi network you will use on your phone.
2. Make sure MySQL is running and `config.php` has the working database settings.
3. Open Terminal in the SmartSlope project folder:

   ```sh
   cd /Applications/MAMP/htdocs/smartslope
   ```

4. Start Apache and MySQL using MAMP. Verify http://localhost:8888/smartslope/
   on the Mac before trying the phone. Use your actual Apache port.

## 2. Open the site on your phone

1. Find the Mac's current Wi-Fi IP address:

   ```sh
   ipconfig getifaddr en0
   ```

2. Connect the phone to the same non-guest Wi-Fi as the Mac. Turn off any VPN while testing.
3. In the phone browser, open `http://<MAC-IP>:8888/smartslope/`. For example, if the Mac IP is `192.168.1.9`, open:

   ```text
   http://192.168.1.9:8888/smartslope/
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
