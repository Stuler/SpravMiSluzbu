FROM nginx:1.27-alpine

COPY ./.docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./.docker/nginx/conf.d/ /etc/nginx/conf.d/
COPY ./.docker/nginx/sites/ /etc/nginx/sites-available/

EXPOSE 80
