# OpenCrawl
This project manages a queue of webpages to crawl. When Crawling, it communicates with an instance of the OpenSearchEngine
PuppeteerAPI to get the DOM and a screenshot of each page.
This project Parses HTML documents to get relevant terms, links, images, etc to add to the index. The OpenIndex will communicate
with this application to do all of the heavy lifting.