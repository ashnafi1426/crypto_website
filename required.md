Software Requirements Specification (SRS)
Crypto Trading Simulation Website
1. Introduction
1.1 Purpose

The purpose of this document is to describe the requirements for the Crypto Trading Simulation Website.

The platform will allow users to simulate cryptocurrency trading using virtual funds. Users will be able to register, view market prices, buy and sell cryptocurrencies, and manage their portfolios.

The system will also include an administrative dashboard that allows administrators to control users, manage balances, update cryptocurrency prices, and monitor trading activity.

1.2 Scope

The Crypto Trading Simulation Website will provide a web-based trading environment where users can practice cryptocurrency trading without using real money.

The system will simulate features of real exchanges such as:

User accounts

Wallet balances

Cryptocurrency trading

Market price updates

Transaction records

Portfolio tracking

This system is designed for educational purposes and trading practice.

1.3 Definitions and Acronyms
Term	Description
Cryptocurrency	Digital currency such as Bitcoin or Ethereum
Exchange	Platform where cryptocurrencies are traded
Wallet	Storage for crypto assets
Trade	Buying or selling cryptocurrency
Portfolio	Collection of assets owned by a user
Admin	System administrator
2. Overall Description
2.1 Product Perspective

The system will be a web-based application consisting of three main components:

Frontend Interface

Backend Server

Database System

System architecture:

User Browser
     │
Frontend (React / HTML / CSS)
     │
Backend API (Laravel / Node.js)
     │
Database (MySQL)

The backend will handle authentication, trading logic, and database operations.

2.2 Product Functions

Main functions of the system include:

User Functions

Register account

Login and logout

View cryptocurrency market

Buy cryptocurrency

Sell cryptocurrency

View portfolio

View transaction history

Admin Functions

Manage users

Adjust user balances

Update cryptocurrency prices

View system transactions

Manage trading activities

2.3 User Classes
Guest User

Permissions:

View homepage

Register account

Login

Registered User

Permissions:

Access dashboard

Buy and sell cryptocurrency

View wallet balance

View portfolio

View transaction history

Administrator

Permissions:

Manage all users

Edit user balances

Modify crypto prices

Monitor trades

Ban or activate users

2.4 Operating Environment

The system will operate on the following platforms:

Frontend:

HTML5

CSS3

JavaScript

React.js

Backend:

Laravel or Node.js

Database:

MySQL

Browser Support:

Google Chrome

Mozilla Firefox

Microsoft Edge

Safari

Hosting:

Cloud servers (AWS, DigitalOcean, or Vercel)

3. System Features
3.1 User Registration
Description

New users can create accounts to access the platform.

Inputs

Name

Email

Password

Process

User submits registration form

System validates input

Password is encrypted

User account is created

Default balance is assigned

Output

User account successfully created.

3.2 User Login
Description

Registered users can log in to access their accounts.

Inputs

Email

Password

Process

Validate credentials

Authenticate user

Generate session/token

Output

User dashboard displayed.

3.3 View Crypto Market
Description

Users can view available cryptocurrencies and their prices.

Displayed information:

Cryptocurrency name

Symbol

Current price

Price change

Market trends

Example coins:

Bitcoin

Ethereum

Solana

BNB

3.4 Buy Cryptocurrency
Description

Users can buy cryptocurrency using virtual funds.

Inputs

Selected coin

Amount to buy

Process

Check user's available balance

Calculate total cost

Deduct balance

Add crypto to wallet

Record transaction

Output

Purchase completed successfully.

3.5 Sell Cryptocurrency
Description

Users can sell cryptocurrencies from their wallets.

Inputs

Selected coin

Amount to sell

Process

Verify wallet balance

Calculate selling value

Deduct crypto amount

Add virtual currency to user balance

Record transaction

Output

Sale completed successfully.

3.6 Portfolio Management
Description

Users can view their crypto holdings.

Information displayed:

Total account value

Crypto assets owned

Asset distribution

Profit and loss

3.7 Transaction History
Description

Users can view previous trading activities.

Displayed information:

Transaction ID

Trade type

Cryptocurrency

Amount

Price

Date

3.8 Admin Dashboard
Description

Administrators can manage the entire system.

Admin capabilities include:

View all users

Modify user balances

Change cryptocurrency prices

Monitor trading transactions

Suspend or activate accounts

4. External Interface Requirements
4.1 User Interface

The system interface includes the following pages:

Public pages:

Home page

Login page

Registration page

User pages:

Dashboard

Market page

Trading page

Portfolio page

Transaction history

Admin pages:

Admin dashboard

User management

Price management

System monitoring

Example dashboard layout:

------------------------------------------------
Navigation Bar
------------------------------------------------
Total Balance
Market Overview
Buy/Sell Panel
------------------------------------------------
Crypto Chart
------------------------------------------------
Transaction History
------------------------------------------------
5. Non-Functional Requirements
5.1 Performance

System should support multiple concurrent users.

Page response time should be less than 3 seconds.

5.2 Security

The system should implement:

Password encryption

Secure authentication

Role-based access control

Input validation

5.3 Usability

The system interface must be:

User-friendly

Responsive

Accessible on mobile devices

5.4 Reliability

The system should provide continuous service with minimal downtime.

6. Database Requirements

The system database will store:

Main tables:

Users

Cryptocurrencies

Wallets

Trades

Transactions

Price history

Example database relationship:

Users
 ├── Wallets
 ├── Trades
 └── Transactions

Cryptocurrencies
 ├── Wallets
 ├── Trades
 └── Price History
7. Future Enhancements

Possible future improvements include:

Mobile application

Real crypto wallet integration

Advanced trading analytics

Automated trading bots

Real-time price feeds

8. Assumptions and Dependencies

Assumptions:

Users have internet access.

Server infrastructure is available.

Dependencies:

Database server

Backend framework

Hosting environment