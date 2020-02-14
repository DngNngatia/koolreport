const puppeteer = require('puppeteer');
// process.argv.forEach((val, index) => {
//   console.log(`${index}: ${val}`);
// });
const jsonStr = process.argv[2];
// console.log(jsonStr);
const requestParams = JSON.parse(jsonStr);

async function render() {
  const defaultParams = {
    cookies: [],
    scrollPage: false,
    emulateScreenMedia: true,
    ignoreHttpsErrors: false,
    html: null,
  };
  
  const params = Object.assign({}, defaultParams, requestParams);

  if (params.pdf.width && params.pdf.height) {
	  params.pdf.format = undefined;
  }
  const launchCfg = {
    // executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome',
    ignoreHTTPSErrors: params.ignoreHttpsErrors,
    args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
  };
  if (params.chromeBinary)
    launchCfg.executablePath = params.chromeBinary;
  const browser = await puppeteer.launch(launchCfg);
  const page = await browser.newPage();

  try {
    await page.setViewport(params.viewport);
    if (params.emulateScreenMedia) {
      await page.emulateMedia('screen');
    }
    params.cookies.map(async (cookie) => {
      await page.setCookie(cookie);
    });

    if (params.html) {
      await page.goto(`data:text/html,${params.html}`, params.goto);
    } else {
      await page.goto(params.url, params.goto);
    }

    await page.pdf(params.pdf);
  } catch (err) {
    throw err;
  } finally {
    await browser.close();
  }
}

render();