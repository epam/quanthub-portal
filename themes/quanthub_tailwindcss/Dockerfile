FROM node:alpine
ENTRYPOINT sh
WORKDIR /code
RUN yarn global add tailwindcss \
  && chown node:node -R /code
COPY --chown=node:node package.json .
USER node
RUN yarn install && yarn cache clean
COPY --chown=node:node . .
