# Problem Statement

Build a full-stack analytics dashboard for a restaurant platform with:
1. Backend in Laravel/PHP
2. Frontend in React

Refer to the mock data JSON files (restaurants.json, orders.json) for 4 restaurants and 200 orders over 7 days.
1. https://drive.google.com/file/d/1luoLVYPwtoZT11KXphdqwogROs7Fk10N/view
2. https://drive.google.com/file/d/1dWupxuuqRktDLhbWkjDxasxtElYvDYYo/view

The dashboard should allow users to:
1. View a list of restaurants and search/sort/filter them.
2. Select a restaurant to view order trends for a given date range: 
- Daily Orders count
- Daily Revenue
- Average Order Value
- Peak Order Hour per day
3. View Top 3 Restaurants by Revenue for a given date range.
4. Apply filters (restaurant, date range, amount range, hour range).

Expectations:
1. Design and implement the API endpoints and frontend to consume them.
2. Handle large datasets efficiently (consider caching, aggregation, and pagination).
3. Include a minimal deployment or instructions for running locally.