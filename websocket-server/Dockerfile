FROM node:20-alpine
RUN apk add --no-cache ffmpeg
WORKDIR /app
COPY package.json ./
RUN npm install
COPY server.js ./
EXPOSE 8081 8082
CMD ["node", "server.js"]
